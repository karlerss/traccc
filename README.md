# traccc - A CLI time tracker with git commit watcher

### Getting started

```
composer install
touch db.sqlite
php traccc up

# optional: add alias to .bashrc

alias t='/path/to/traccc'
```

### Usage

```
USAGE:
   traccc <OPTIONS> <COMMAND> ...

   A CLI time tracker with git commit watcher


OPTIONS:
   -v, --version            print version

   -h, --help               Display this help screen and exit immediately.

   --no-colors              Do not use any colors in output. Useful when piping output
                            to other tools or files.

   --loglevel <level>       Minimum level of messages to display. Default is info.
                            Valid levels are: debug, info, notice, success, warning,
                            error, critical, alert, emergency.


COMMANDS:
   This tool accepts a command as first parameter as outlined below:


   start <OPTIONS>

     Start tracking


     -w, --watch              Watch for git commits in working directory


   stop [<message>]

     Finish tracking


     <message>                Message

   status

     Show status


   report

     Show entries


   up

     Create db tables

```