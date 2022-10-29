<?php

namespace Amp\DoH;

enum NameserverType
{
    case RFC8484_GET;
    case RFC8484_POST;
    case GOOGLE_JSON;
}
