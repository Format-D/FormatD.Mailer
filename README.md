# FormatD.Mailer

A Template Mailer for Neos Flow or/and CMS Projects.

## What does it do?

This package provides a service class intended to be used as base class for sending Fluid templates as mails.
In addition it contains a debugging aspect for cutting off and/or redirecting all mails (sent by SymfonyMailer) in a
development environment.
Moreover, it supports using environment variables to build the DSN for the Symfony Mailer.

## Kompatiblität

Versioning scheme:

     1.0.0 
     | | |
     | | Bugfix Releases (non breaking)
     | Neos Compatibility Releases (non breaking except framework dependencies)
     Feature Releases (breaking)

Releases und compatibility:

| Package-Version | Neos Flow Version | `neos/form` Version |
|-----------------|-------------------|---------------------|
| 2.0.x           | >= 8.x            | >= 6.0.0            |
| 1.1.x           | >= 6.x            | < 6.0.0             |
| 1.0.x           | 4.x - 5.x         | < 6.0.0             |

## Using the service in your own plugins to use fluid templates for mails

Configure default from address:

```
FormatD:
  Mailer:
    defaultFrom:
      address: 'example@example.com'
      name: 'Example'
```

Extend AbstractMailerService and add methods as needed following the example of sendTestMail().

## intersept all mails in a dev environment

Configure swiftmailer to intersept all mails send by your neos installation (not only by the service).

This is an example which intercepts all mails and redirects them to example@example.com and secondexample@example.com:

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

## Handling Embedded Images

The method `AbstractMailerService->setMailContentFromStandaloneView()` has a parameter to embed all images into the mail
body.
This is handy if you have an installation that is protected by a .htaccess file for example, or the user may not have
access to the internet when reading the email. GMail also cannot display images if they are included from a local
domain.

FormatD.Mailer can be configured to replace all image urls by local paths (if you specify a baseUrl in your flow
configuration).
This is needed it the server cannot make a web-request to itself (also maybe due to a .htaccess or something).

```
FormatD:
  Mailer:
    localEmbed: true
```

## Disable embed for specific images

You can disable image embedding for specific images by adding `data-fdmailer-embed="disable"` as data attribute to the
image tag.
This is useful for tracking pixels where you dont want the local embedding.

## Using environment variables to build a SMTP transport DSN

Use the special DSN `fd-mailer` in the SymfonyMailer configuration. This transport will then use the configuration
given under `FormatD.Mailer.smtpTransport` to build the actual DSN and actual SmtpTransport object. `host` is mandatory,
all other parts are optional.

For special cases the automatic DSN construction can be omitted by passing the DSN directly at
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
       
      # Optional: Query params appended to the DSN, use with caution and only when necessary
      options:
        # Do not verify server TLS certificate
        verify_peer: 0
        # Do not try `STARTTLS` at all
        auto_tls: 'false'
        # Use `STARTTLS` even when not announced in server capabilities
        require_tls: 'true'
        
```

The `encryption` param is cast to boolean, with the `'false'` string being interpreted falsy as well. If true, the
scheme will be `smtps`, thus the Symfony SMTP transport tries to establish a TLS encrypted channel right away. If
`encryption` is false, then the transport will still try to upgrade the connection via `STARTTLS`, when that is
announced by the server!

The `options` config is completely optional and could be omitted to use SymfonyMailer's sane defaults. It can be used to
define query parameters appended to the DSN, as described in
the [SymfonyMailer documentation](https://symfony.com/doc/current/mailer.html).