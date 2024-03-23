<?php

namespace ThingsDB\enum;

enum ResponseType: int
{
    case PONG = 16;
    case OK = 17;
    case DATA = 18;
    case ERROR = 19;

    case NODE_STATUS = 0;
    case WARNING = 5;
    case ON_JOIN = 6;
    case ON_LEAVE = 7;
    case ON_EMIT = 8;
    case ON_DELETE = 9;
}
