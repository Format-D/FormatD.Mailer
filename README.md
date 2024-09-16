
# FormatD.Mailer

A template mailer using Symfony Mailer to configure, sent and intercept e-mails in Flow and Neos.


## What does it do?

This package provides a service class intended to be used as base class for sending fusion templates as mails.
In addition it contains a debugging aspect for cutting off and/or redirecting all mails in a development environment.


## Using the service in you own plugins to use fluid templates for mails

Configure smtp data (`dsn`) and default from address (`defaultFrom`):

```
FormatD:
  Mailer:
  	dsn: 'smtp://user:pass@smtp.example.com:25'
    defaultFrom:
      address: 'example@example.com'
      name: 'Example'
```

Extend AbstractMailerService and add methods as needed following the example of sendTestMail().

## Use `FormatD.Mailer:Document.Email` node type as e-mail templates
You can create e-mail templates from the Neos backend by choosing and creating and "E-Mail" document node. This node can either 
* be referenced by its ID in the Settings.yaml to be sent via some controller action
* or chosen in the Form Finisher `FormatD.Mailer:EmailFinisher` under "E-Mail Template" to select an e-mail template for sending in the Form Builder

### Get form values in e-mail templates
In the e-mail node in Neos, you can set markers to reflect the form values you want to submit.
E.g., you have a form builder form with the elements (ids!) "firstName" and "lastName" and want to display the values in your e-mail. You can write any text and set markers, like so:
First name: ###firstName###
Last name: ###lastName###
Topics: ###i-need-help-with.topic###

### Overriding e-mail template
The e-mail templates consist of various fusion prototypes which you can extend or completely override in your site package as you see fit.
`FormatD.Mailer:Document.Email` is a `Neos.Neos:Page` with multiple fragments for the e-mail head and body.

## Intercept all e-mails in a dev environment

Configure mailer to intercept all mails send by your Neos installation (not only by the service).

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


## Handling embedded images

The method `AbstractMailerService->setMailContentFromStandaloneView()` has a parameter to embed all images into the mail body.
This is handy if you have an installation that is protected by a .htaccess file for example, or the user may not have access to the internet when reading the email. GMail also cannot display images if they are included from a local domain.

FormatD.Mailer can be configured to replace all image urls by local paths (if you specify a baseUrl in your flow configuration).
This is needed it the server cannot make a web-request to itself (also maybe due to a .htaccess or something).

```
FormatD:
  Mailer:
    localEmbed: true
```

## Disable embedding for specific images

You can disable image embedding for specific images by adding `data-fdmailer-embed="disable"` as data attribute to the image tag. 
This is useful for tracking pixels where you dont want the local embedding.


## Compatibilty

Versioning scheme:

     1.0.0 
     | | |
     | | Bugfix Releases (non breaking)
     | Neos Compatibility Releases (non breaking except framework dependencies)
     Feature Releases (breaking)

Releases und compatibility:

| Package Version | Neos Flow Version      |
|-----------------|------------------------|
| 3.0.x           | >= 9.x                 |
| 2.0.x           | >= 8.x < 9.x           |
| 1.1.x           | >= 6.x                 |
| 1.0.x           | 4.x - 5.x              |