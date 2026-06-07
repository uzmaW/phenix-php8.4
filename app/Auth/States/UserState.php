<?php

namespace App\Auth\States;

enum UserState
{
    case Guest;
    case LoggingIn;
    case Authenticated;
    case Banned;
}
