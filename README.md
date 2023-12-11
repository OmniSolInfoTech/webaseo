# WEBASEO  

![Packagist Version (custom server)](https://img.shields.io/packagist/v/osit/webaseo)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/osit/webaseo/php)
![Static Badge](https://img.shields.io/badge/php-Laravel-purple)
![License](https://img.shields.io/github/license/omnisolinfotech/webaseo)


## About WebaSEO

SEO report for your website.


## Using WebaSEO

You can use WebaSEO to improve your SEO. WebaSEO will grade your website and give you insight on what you may improve to improve SEO.


## Getting Started

1. Composer require the package:  
   ````shell
   composer require osit/webaseo 
   ````
2. Initialise the package:  
   ````shell
   php artisan webaseo:init
   ````

3. Use WebaSEO:
   1. visit WebaSEO Dashboard using path {/webaseo/report} e.g.: http://127.0.0.1:8000/webaseo/report to get your SEO report!

## Security
You can protect you WebaSEO data by uncommenting the line of code:  
``// $this->middleware('auth');``  
in the WebaseoController.php constructor.


[//]: # (add documnetation for auth - as per the WebaliticsController)

## Contributing

Thank you for considering contributing to the WebaSEO package!  
Please email our developer via [developer@osit.co.za](mailto:developer@osit.co.za) to find out how.