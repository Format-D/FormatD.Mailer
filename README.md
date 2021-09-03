
# FormatD.Mailer

A Template Mailer for Neos Flow or/and CMS Projects.


## What does it do?

This package provides a service class intended to be used as base class for sending fusion templates as mails.
In addition it counatins a debugging aspect for cutting off and/or redirecting all mails (sent by Swiftmailer) in a development environment.


## Using the service in you own plugins to use fluid templates for mails

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

The method `AbstractMailerService->setMailContentFromStandaloneView()` has a parameter to embed all images into the mail body.
This is handy if you have an installation that is protected by a .htaccess file for example, or the user may not have access to the internet when reading the email. GMail also cannot display images if they are included from a local domain.

FormatD.Mailer can be configured to replace all image urls by local paths (if you specify a baseUrl in your flow configuration).
This is needed it the server cannot make a web-request to itself (also maybe due to a .htaccess or something).

```
FormatD:
  Mailer:
    localEmbed: true
```

## Disable embed for specific images

You can disable image embedding for specific images by adding `data-fdmailer-embed="disable"` as data attribute to the image tag. 
This is useful for tracking pixels where you dont want the local embedding.