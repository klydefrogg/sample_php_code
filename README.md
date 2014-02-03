sample_php_code
===============

This is a sample of my PHP work. The scripts work thusly:

1. An image request comes to the server. The request produces an error 404.
2. With .htaccess rewrite for PNG and JPG images, the error is handled by handleimagerequest.php
3. The script parses the request information and looks for an existing image that already exists matching the parameters
4. If one is found, the script outputs the image and exits.
5. If no existing image is found, the Image class is invoked, leveraging the Imagick library to create the requested image size.
6. The new image is output and the script exits.
