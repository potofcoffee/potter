# potter
Auto-create .pot translation templates for WordPress plugins

## Setup
This is assumes that you already have a WordPress plugin using Composer. In this case, you'll just have to type the following command in your plugin's root directory.

    composer require peregrinus/potter
    
Composer will install the potter script into the vendor/peregrinus/potter folder. Unfortunately,
at this moment, composer still fails to assign the correct permissions on Linux, so you'll have to do a second command:

    chmod +x vendor/peregrinus/potter/potter
    
## Usage
On Linux, execute the following command in the root directory of your plugin:

    vendor/peregrinus/potter/potter
    
On windows, you would use the following command:

    php vendor\peregrinus\potter\potter

Potter will figure out the correct name and path for your .pot file from the plugin's metadata,
most notably "Domain Path" and "Text Domain". It will then scan all php files
in your plugin's folder and all subfolders and extract all relevant texts.
