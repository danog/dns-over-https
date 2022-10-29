<?php

namespace Amp\DoH;

use Amp\Dns\ConfigException;

enum NameserverType
{
    case RFC8484_GET;
    case RFC8484_POST;
    case GOOGLE_JSON;
}
