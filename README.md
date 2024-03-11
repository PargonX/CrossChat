croschat Extention fork of mChat
=====================

[![Build Status](https://travis-ci.org/kasimi/mChat.svg?branch=master)](https://travis-ci.org/kasimi/mChat)

## Setup
Instructions to install crosschat:

1: Download and upload CrossChatBB.jar to your Minecraft Server
2: Download DiscordSRV and set up the chat channel back and forth between it and your discord server
3: In the CrossChat.yml file found in CrossChat plugin folder (created after running), you will find a the following line:
    ForumURL = ""
   Please input your forum url into here
4:Download The latest release of crosschat.zip
5:Upload the dmzx folder to your phpbb etx folder
6:Open the upload folder and upload all files to the root of your phpbb forum
7:change the files you just uploaded to included your database login information (ignoring sendtomc.php)
8:Open file mchat.php found in the dmzx folder you placed in extentions
  on line 363 change forum.example.com $apisendurl to your forum url leaving the rest alone in the url provided
9: Download and start your python webserver on port 8081 with uvicorn:
  ```uvicorn main:app --reload --host 0.0.0.0 --port 8081````
