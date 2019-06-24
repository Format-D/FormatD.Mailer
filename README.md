
# FormatD.Mailer

A Template Mailer for Neos Flow or/and CMS Projects.


## What does it do?

This package provides a service class intended to be used as base class for sending fusion templates as mails.
And a debugging aspect for cutting off and/or redirecting all mails (sent by Swiftmailer) in a development environment.


## Using the service in you own plugins

Extend AbstractMailerService and add methods as needed following the example of sendTestMail().


## Configuration

This is an example which intercepts all mail and redirects them to example@example.com and secondexample@example.com

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
