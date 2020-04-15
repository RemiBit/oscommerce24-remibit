OSCOMMERCE 2.4 (JOLI) REMIBIT MODULE

INSTALLATION AND CONFIGURATION

In order to install the module, it is necessary to access the server where the web files are hosted by ssh. If you don’t know how to do that, please contact your site administrator or your hosting provider.

In this example we will be using the default oscommerce configuration, so the website files are located in /var/www/html/oscommerce. Please replace [oscommerce] with the actual name of your website directory.

## Requirements

* A RemiBit merchant account
* oscommerce 2.4


## Installation

1/. Go to the oscommerce directory

```
cd /var/www/html/oscommerce
```

2/. Fetch the RemiBit module

```
sudo wget https://github.com/RemiBit/oscommerce24-remibit/releases/download/v1.0/oscommerce24-remibit.zip
```

3/. Uncompress it

```
sudo unzip oscommerce24-remibit.zip
```

Please make sure you are on your {WEBROOT} directory before uncompressing. From it, you should see LICENSE.md and README.md files and catalog and docs directories.

The RemiBit module is now prepared to be installed.

4/. Login to Admin dashboard, go to Legacy > Modules > Payment and click on Install Module button in the top right. 

Scroll down until you find RemiBit Payment Method and select it.

Click on Install Module button. The module will not load until it is configured.


## Configuration

Click on the Edit button and fill up the RemiBit authentication information from your RemiBit merchant account's Settings > Gateway:

* API Login ID
* API Transaction Key
* Signature Key
* MD5 Hash


