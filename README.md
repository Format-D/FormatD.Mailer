# FormatD.Mailer

A template mailer for Neos Flow and Neos CMS projects.

## Features

- Base service class for sending Fluid templates as emails
- Email documents edited in Neos, rendered with Fusion and MJML
- Form finisher that sends an email document, with form values and attachments
- Interception and redirection of all emails sent by Symfony Mailer (e.g. in a development or review environment)
- Optional logging of sent emails
- Embedding of images into the email body
- SMTP transport DSN built from environment variables
- Additional Symfony transport factories for API-based transports
- CLI commands to send test emails

## Compatibility

Versioning scheme:

     1.0.0
     | | |
     | | Bugfix releases (non-breaking)
     | Neos compatibility releases (non-breaking except framework dependencies)
     Feature releases (breaking)

Releases and compatibility:

| Package version | Framework                   | `neos/form` version |
|-----------------|-----------------------------|---------------------|
| 3.0.x           | Neos 9.x (`neos/neos`)      | >= 6.0.0            |
| 2.1.x           | Neos 8.x (`neos/neos`)      | >= 6.0.0            |
| 2.0.x           | Neos Flow 8.x (`neos/flow`) | >= 6.0.0            |
| 1.1.x           | Neos Flow >= 6.x <= 8.x     | < 6.0.0             |
| 1.0.x           | Neos Flow 4.x - 5.x         | < 6.0.0             |

The package requires PHP 8.3 or later. `neos/form` is only needed for the form finisher and the attachment form
element.

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
`DefaultMailerService` is a ready-made subclass, which can be injected and used without extending anything.

To send an email without a template, use `send()`. It takes the subject, recipient, sender, text body, HTML body,
reply-to, CC, BCC and a list of attachments (`Symfony\Component\Mime\Part\DataPart`). Without a sender, `defaultFrom`
is used.

```php
$this->mailerService->send('Subject', 'recipient@example.com', null, 'Text body', '<p>HTML body</p>');
```

## Email documents

The node type `FormatD.Mailer:Document.Email` ("Email" in the Neos page tree) is an email edited like a page. It has a
header, a main and a footer content collection and is rendered with an MJML-based layout in Fusion.

To send an email document, `AbstractMailerService->getNodeById()` reads the node from the live workspace (hidden nodes
included), and `getHtml()` fetches its rendered HTML over HTTP from its public URI. This needs:

- `Neos.Flow.http.baseUri`, so the URI can be built on the command line too
- the site the email documents belong to; on Neos 9 also its content repository:

```
FormatD:
  Mailer:
    site:
      siteNodeName: 'my-site'
      contentRepositoryId: 'default'
```

In a Flow context containing `Development`, the TLS certificate of the site is not verified when the HTML is fetched.

## Form finisher

The form finisher `FormatD.Mailer:EmailFinisher` ("Email Finisher" in the Neos form builder) sends an email document
when a form is submitted. In its inspector, select the email document under "E-Mail Template".

- Markers in the email document are replaced with form values: `###identifier###` with the value of the form element
  `identifier`, `###identifier.key###` with one key of an array value. Values are HTML-escaped; lists are joined with
  commas.
- The sender defaults to `defaultFrom`, the recipient to `defaultTo`:

  ```
  FormatD:
    Mailer:
      defaultTo:
        address: 'office@example.com'
        name: 'Office'
  ```

- Format `html` sends the HTML only. `plaintext` sends a text version only, `multipart` sends both. The text version is
  derived from the HTML.
- When "attach uploads" (`attachAllPersistentResources`) is set, every uploaded file of the form is attached, and the
  file of every "Attachment" element. The finisher option `attachments` attaches fixed files (`resource: <path>`) or
  the file of one form element (`formElement: <identifier>`), as in the Neos.Form email finisher.

The form element `FormatD.Mailer:AssetAttachment` ("Attachment") lets the editor select an asset from the media module,
which is attached to the email. Visitors cannot change which asset is attached.

## Intercept all emails in a development environment

Configure the mailer to intercept all emails sent by your Neos installation, not only those sent by the service.

This example intercepts all emails and redirects them to `example@example.com` and `secondexample@example.com`:

```
FormatD:
  Mailer:
    interceptAll:
      active: true
      recipients: ['example@example.com', 'secondexample@example.com']
      noInterceptPatterns: ['/@example\.org$/']
    bccAll:
      active: false
      recipients: []
```

An intercepted email goes to the first address in `recipients`; all other addresses get it as CC. Its original CC and
BCC are removed, and its subject starts with `[intercepted <original recipient> CC: <original CC> BCC: <original BCC>]`.

`noInterceptPatterns` are regular expressions. When one matches the first original recipient, the email is sent to
that recipient, but its CC and BCC are removed.

`bccAll` adds the listed addresses as BCC to every email. It works with or without `interceptAll`.

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

Images can be attached to the email as inline parts, so the email body shows them without loading them from the web.
This helps when the installation is protected, for example by an `.htaccess` file, or when the recipient has no
internet access while reading the email. Gmail also cannot display images included from a local domain.

```
FormatD:
  Mailer:
    attachEmbeddedImages: true
```

With this setting, `send()` attaches every image of the HTML body. For Fluid templates, pass `true` as the third
parameter of `AbstractMailerService->setMailContentFromStandaloneView()`.

On the command line, relative image paths are read from the `Web` directory. FormatD.Mailer can also read images with
absolute URLs from the local disk: it removes `Neos.Flow.http.baseUri` from the URL and reads the rest from the `Web`
directory. This is needed if the server cannot make a web request to itself, for example because of an `.htaccess`
file.

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
        verify_peer: 0
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

The aspect `FormatD\Mailer\Aspect\MailerTransportAspect` builds the transport for every DSN and adds the interception.
A transport passed directly to `Neos\SymfonyMailer\Service\MailerService->getMailer()` is used as given, unless
`interceptAll` or `bccAll` is active.

## Sending test emails

```
./flow mailer:sendtest --to recipient@example.com
./flow mailer:sendtestfromtemplatenode --to recipient@example.com
```

`mailer:sendtest` sends a Fluid template. `mailer:sendtestfromtemplatenode` sends the email document configured in
`FormatD.Mailer.templateNodes.test` (its node identifier).
