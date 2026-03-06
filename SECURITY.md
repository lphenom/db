# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 0.1.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability, **do not open a public issue**.

Please report it by emailing: **popkovd.o@yandex.ru**

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

You will receive a response within **72 hours**. We will work with you to understand and address the issue promptly.

## Security Best Practices

- Never put credentials in source code.
- Use environment variables for DSN, username, password.
- All SQL parameters must be bound via `ParamBinder` — never interpolated.

