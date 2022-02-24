# Session authentication for your API

When you expose an API you often have a different configuration for authentication. 
Most APIs use some kind of session-less authentication using tokens of some kind.

This component implements such session-less authentication where the token is the session key.
It works by shortly opening the session to extract the relevant data, then aborting it using `session_abort()`. This means
there is no write done to the session and locking (if using the standard file backend) is kept to a minimum.
