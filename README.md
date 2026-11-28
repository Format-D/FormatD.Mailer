# FormatD.Mailer

A template mailer for Neos Flow and Neos CMS projects.

## Features

- Base service class for sending Fluid templates as emails
- Interception and redirection of all emails sent by Symfony Mailer (e.g. in a development or review environment)
- Optional logging of sent emails
- Embedding of images into the email body
- SMTP transport DSN built from environment variables
- Additional Symfony transport factories for API-based transports

## Compatibility

Versioning scheme:

     1.0.0
     | | |
     | | Bugfix releases (non-breaking)
     | Neos compatibility releases (non-breaking except framework dependencies)
     Feature releases (breaking)

Releases and compatibility:

| Package version | Neos Flow version | `neos/form` version |
|-----------------|-------------------|---------------------|
| 3.0.x           | 9.x               | >= 6.0.0            |
| 2.0.x           | 8.x               | >= 6.0.0            |
| 1.1.x           | >= 6.x <= 8.x     | < 6.0.0             |
| 1.0.x           | 4.x - 5.x         | < 6.0.0             |

## Using the service to send Fluid templates as emails

Configure the default sender address:

```
FormatD:
  Mailer:
    defaultFrom:
      address: 'example@example.com'
      name: 'Example'
```

Extend `AbstractMailerService` and add methods as needed, following the example of `sendTestMail()`.

## Intercept all emails in a development environment

Configure the mailer to intercept all emails sent by your Neos installation, not only those sent by the service.

This example intercepts all emails and redirects them to `example@example.com` and `secondexample@example.com`:

```
FormatD:
  Mailer:
    interceptAll:
      active: true
      recipients: ['example@example.com', 'secondexample@example.com']
    bccAll:
      active: false
      recipients: []
```

## Logging email dispatch

Logging is off by default. When turned on, every email sent through the `fd-mailer` DSN is logged with its recipient,
sender and subject. The log goes to its own logger channel `FormatD.Mailer:MailerLogger`, which writes to
`Data/Logs/Mailer.log`. Emails sent through any other DSN, including those handled by `additionalTransportFactories`,
are not logged. To turn logging on:

```
FormatD:
  Mailer:
    logging:
      emailDispatch: true
```

If an email was intercepted (see `interceptAll` and `bccAll` above), the log entry still shows the *original*
recipient, sender and subject, not the intercepted ones, because those are the values that matter for debugging. To do
this, the original data is stored in an `X-Intercept-Metadata` email header before the message is rewritten. Change
the header name if it collides with another header:

```
FormatD:
  Mailer:
    interceptAll:
      interceptDataHeaderName: 'X-Intercept-Metadata'
```

## Handling embedded images

The method `AbstractMailerService->setMailContentFromStandaloneView()` has a parameter to embed all images into the
email body.
This helps when the installation is protected, for example by an `.htaccess` file, or when the recipient has no
internet access while reading the email. Gmail also cannot display images included from a local domain.

FormatD.Mailer can be configured to replace all image URLs with local paths (if you specify a baseUrl in your Flow
configuration).
This is needed if the server cannot make a web request to itself, for example because of an `.htaccess` file.

```
FormatD:
  Mailer:
    localEmbed: true
```

## Disabling embedding for specific images

To keep a specific image from being embedded, add the data attribute `data-fdmailer-embed="disable"` to its image tag.
This is useful for tracking pixels.

## Using environment variables to build an SMTP transport DSN

Use the special DSN `fd-mailer` in the Symfony Mailer configuration. This transport then uses the configuration under
`FormatD.Mailer.smtpTransport` to build the actual DSN and SMTP transport. `host` is required; all other parts are
optional.

For special cases, skip the automatic DSN construction by setting the DSN directly in
`Neos.SymfonyMailer.mailer.dsn`.

```
Neos:
  SymfonyMailer:
    mailer:
      dsn: 'fd-mailer'

FormatD:
  Mailer:
    smtpTransport:
      host: '%env:SMTP_HOST%'
      encryption: '%env:SMTP_ENCRYPTION%'
      port: '%env:SMTP_PORT%'
      username: '%env:SMTP_USERNAME%'
      password: '%env:SMTP_PASSWORD%'

      # Optional: query parameters appended to the DSN, use with caution and only when necessary
      options:
        # Do not verify server TLS certificate
        verify_peer: 1
        # Do not try `STARTTLS` at all
        auto_tls: 'false'
        # Use `STARTTLS` even when not announced in server capabilities
        require_tls: 'true'
```

The `encryption` parameter is cast to a boolean; the string `'false'` also counts as false. If true, the scheme is
`smtps`, so the Symfony SMTP transport establishes a TLS-encrypted channel right away. If false, the transport still
upgrades the connection via `STARTTLS` when the server announces it.

The `options` setting is optional; omit it to use the Symfony Mailer defaults. It defines query parameters appended to
the DSN, as described in the [Symfony Mailer documentation](https://symfony.com/doc/current/mailer.html).

## Additional transport factories

Further Symfony transport factories (for example API-based bridges such as `symfony/brevo-mailer`) can be registered
for DSNs other than `fd-mailer`:

```
FormatD:
  Mailer:
    mailer:
      additionalTransportFactories:
        - 'Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory'
```

Install `symfony/http-client` if those transports should share one HTTP client.
