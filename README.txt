# wppt-emailer (WordPress Phil Tanner's Emailer)

Yet another WordPress plugin that helps diagnose and resolve email woes.

Note: Uses 3rd party app Ghost Inspector (for full details, see below).

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

A running copy of WordPress, with Administrator login details. 

This plugin should be platform (Windows/Mac/*Nix) independent, and doesn't require any particular versions of PHP to run.

It does request jQuery and jQueryUI to be loaded from WordPress, though these should already be bundled in all standard (i.e. non-very-specific instances where they've been deliberately disabled) installs.

### Installing

You can install most simply by using the WordPress plugin directory.

Alternately, you can download the lastest zipped copy of the plugin from GitHub:
https://github.com/PhilTanner/wppt-emailer/archive/master.zip

Log in to your WordPress dashboard, navigate to the Plugins menu, and click [Add New] at the top of the page.

Click [Upload Plugin]

Click [Choose File]

Select the ```wppt-emailer-master.zip``` file you've just downloaded.

Click [Install Now]


## Running the tests

Select the [Phil's Emailer] link in the Dashboard navigation.

Enter your SMTP mail settings, and click the Test button.

If it works, you will get a success statement appear on the right. If not - hopefully a meaningful message as to why not.

## Please NOTE!:
The email address receipt is tested by sending the email to a 3rd party external service, Ghost Inspector (https://ghostinspector.com/).

Ghost Inspector is a really useful automated test suite - which provides a way to test applications sending of emails. I recommend you check it out if you want to run any form of automated tests against your web sites.

The mail sent to their server contains only fixed text, with no details about your system (other than the From: address the emails are sent from). The email inbox is deleted after an hour and is not retained.

For more information about their email test gateway, you can view their documentation here:
https://ghostinspector.com/docs/check-email/


## License

This project is licensed under the GPL3 License - see the [LICENSE](LICENSE) file for details

