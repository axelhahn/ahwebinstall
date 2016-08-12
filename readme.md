
# Axel Hahn's PHP Web Installer

## About

This is a generator for an install script for a php project.

The aim is to get an install process that can be defined with a single line.

    wget -O - [url]/installer | php

This installer will download a single zip file and uncompress it in a
subdirectory.

## Author

www.axel-hahn.de

Source: https://github.com/axelhahn/ahwebinstall


## Status

ALPHA: The work is in progress.

Currently the installer dowloads a zip from a given source and uncompresses
it in a subdirectory.

## Usage

### create zip and upload


### create a new project

Go to the projects directory and create a copy of the example.json

<pre>
{
    "generator":{
        
    },
    "installer":{
        "product": "My example tool",
        "source": "http://sourceforge.net/projects/my-example-tool/files/latest/download",
        "installdir": "example-tool",
        "postmessage": "And now ... open http:\/\/localhost\/example-tool\/ in your webbrowser ..."
    }
}
</pre>

Change the settings:

* product: The name of your product ("My example tool")
* source: url of a zip file to download (i.e. "http://sourceforge.net/projects/my-example-tool/files/latest/download")
* installdir: directory where to extract the zip data "example-tool",
* postmessage: message text after successful installation
* tmpzip: name of the downloaded file; maybe this will be reoved "_example-tool-latest.zip"

### generate installer

## TODO

### For generator

* Copy generated installer to another target directory


### For installer

* set permissions to files and dirs
* define pre and post commands

<!--

## IDEAS:

### For generator

* set a source dir to zip + upload 
* Copy generated installer to another target directory

Remark:

An upload to anywhere will be really tricky: ftp/ ssh/ http post/ s3 and handle 
all authentication types ... maybe not.
-->