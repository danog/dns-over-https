<?php

namespace Amp\DoH\Internal;

use Amp;
use Amp\ByteStream\StreamException;
use Amp\Deferred;
use Amp\Dns\DnsException;
use Amp\Dns\TimeoutException;
use Amp\DoH\DoHException;
use Amp\DoH\Nameserver;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Promise;
use LibDNS\Messages\Message;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\Question;
use function Amp\call;

/** @internal */
abstract class Socket
{
    const MAX_CONCURRENT_REQUESTS = 500;

    /** @var array Contains already sent queries with no response yet. For UDP this is exactly zero or one item. */
    private $pending = [];

    /** @var MessageFactory */
    private $messageFactory;

    /** @var callable */
    private $onResolve;

    /** @var array Queued requests if the number of concurrent requests is too large. */
    private $queue = [];

    /**
     * @param string $uri
     *
     * @return Promise<self>
     */

    abstract public static function connect(DelegateHttpClient $httpClient, Nameserver $nameserver): self;

    /**
     * @param Message $message
     *
     * @return Promise<int>
     */
    abstract protected function resolve(Message $message): Promise;

    protected function __construct()
    {
        $this->messageFactory = new MessageFactory;

        $this->onResolve = function (\Throwable $exception = null, Message $message = null) {
            if ($exception) {
                $this->error($exception);
                return;
            }
            \assert($message instanceof Message);

            $id = $message->getId();

            // Ignore duplicate and invalid responses.
            if (isset($this->pending[$id]) && $this->matchesQuestion($message, $this->pending[$id]->question)) {
                /** @var Deferred $deferred */
                $deferred = $this->pending[$id]->deferred;
                unset($this->pending[$id]);
                $deferred->resolve($message);
            }
        };
    }

    /**
     * @param \LibDNS\Records\Question $question
     * @param int $timeout
     *
     * @return \Amp\Promise<\LibDNS\Messages\Message>
     */
    final public function ask(Question $question, int $timeout): Promise
    {
        return call(function () use ($question, $timeout) {
            if (\count($this->pending) > self::MAX_CONCURRENT_REQUESTS) {
                $deferred = new Deferred;
                $this->queue[] = $deferred;
                yield $deferred->promise();
            }

            do {
                $id = \random_int(0, 0xffff);
            } while (isset($this->pending[$id]));

            $deferred = new Deferred;
            $pending = new class {
                use Amp\Struct;

                public $deferred;
                public $question;
            };

            $pending->deferred = $deferred;
            $pending->question = $question;
            $this->pending[$id] = $pending;

            $message = $this->createMessage($question, $id);

            try {
                $response = $this->resolve($message);
            } catch (StreamException $exception) {
                $exception = new DnsException("Sending the request failed", 0, $exception);
                $this->error($exception);
                throw $exception;
            }

            $response->onResolve($this->onResolve);

            try {
                // Work around an OPCache issue that returns an empty array with "return yield ...",
                // so assign to a variable first and return after the try block.
                //
                // See https://github.com/amphp/dns/issues/58.
                // See https://bugs.php.net/bug.php?id=74840.
                $result = yield Promise\timeout($deferred->promise(), $timeout);
            } catch (Amp\TimeoutException $exception) {
                unset($this->pending[$id]);
                throw new TimeoutException("Didn't receive a response within {$timeout} milliseconds.");
            } finally {
                if ($this->queue) {
                    $deferred = \array_shift($this->queue);
                    $deferred->resolve();
                }
            }

            return $result;
        });
    }

    private function error(\Throwable $exception)
    {
        if (empty($this->pending)) {
            return;
        }

        if (!$exception instanceof DnsException && !$exception instanceof DoHException) {
            $message = "Unexpected error during resolution: ".$exception->getMessage();
            $exception = new DnsException($message, 0, $exception);
        }

        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $pendingQuestion) {
            /** @var Deferred $deferred */
            $deferred = $pendingQuestion->deferred;
            $deferred->fail($exception);
        }
    }

    final protected function createMessage(Question $question, int $id): Message
    {
        $request = $this->messageFactory->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);
        $request->setID($id);
        return $request;
    }

    private function matchesQuestion(Message $message, Question $question): bool
    {
        if ($message->getType() !== MessageTypes::RESPONSE) {
            return false;
        }

        $questionRecords = $message->getQuestionRecords();

        // We only ever ask one question at a time
        if (\count($questionRecords) !== 1) {
            return false;
        }

        $questionRecord = $questionRecords->getIterator()->current();

        if ($questionRecord->getClass() !== $question->getClass()) {
            return false;
        }

        if ($questionRecord->getType() !== $question->getType()) {
            return false;
        }

        if ($questionRecord->getName()->getValue() !== $question->getName()->getValue()) {
            return false;
        }

        return true;
    }
}
