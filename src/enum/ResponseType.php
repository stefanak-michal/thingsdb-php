<?php

namespace ThingsDB\enum;

enum ResponseType: int
{
    case PONG = 16;
    case OK = 17;
    case DATA = 18;
    case ERROR = 19;
}
