Database Migrations
========
* Version: Pre Release
* Authors: David Anderson (davjand) & Tom Johnson (jetbackwards)

The aim of this extension is to be able to perform database migrations in the following situations:

* Between multiple developers working on the same repo (each with local test environments)
* From local test environments => staging server
* From local test environments => live server
* From local test environments => integration testing server

To work, the migration data is stored into the workspace folder which should be tracked by git.

It needs custom git ignore settings to work correctly (to keep the local tracking out of git).

Please see davjand/sym-workspace for the build environment

Why not track changes in the symphony mysql database you say? Executing queries to save symphonies queries is current a big no-no due to this invalidating the mysql last entry functionality