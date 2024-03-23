<?php

namespace ThingsDB\enum;

enum PackageType: int
{
    case PING = 32;
    case AUTH = 33;
    case QUERY = 34;
    case RUN = 37;
    case JOIN = 38;
    case LEAVE = 39;
    case EMIT = 40;
}
