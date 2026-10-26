# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 0.1.x | Yes |

## Reporting a vulnerability

Please report security problems privately by email to b@wagner.codes, not in a
public issue. Include the version, a description of the problem and, if you
can, a minimal script that shows it. You will get an acknowledgement within
five business days.

A wrong due date is a correctness bug, and can matter as much as a security
bug for anyone relying on this library. Report those the same way if the
details are sensitive; otherwise a public issue is fine.

## What the library does with data

It computes dates. It makes no network calls, writes nothing to disk, logs
nothing, and reads no secrets. The Laravel bridge reads only its own
`statutory-deadlines` config keys and validates each one when the factory is
first resolved.
