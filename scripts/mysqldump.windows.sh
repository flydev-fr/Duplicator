@echo off

REM (1) Set up all the mysqldump variables
set DBSERVER=%%SERVER%%
set DATABASE=%%DATABASE%%
set USER=%%USER%%
set PASS=%%PASS%%
set FILE=%%FILE%%
set CACHEPATH=%%CACHEPATH%%
REM set PORT=%%PORT%%

REM Fix trailing slash in cache path
set lastchar=%CACHEPATH%:~-1%
if %lastchar% == \ (
    set OUTPUT=%CACHEPATH%%FILE%
)
else (
    set OUTPUT=%CACHEPATH%\%FILE%
)

REM (3) Do the MySQL database backup (dump)
REM  - to log errors of the dump process to a file, add --log-error=mysqldump_dup_error.log
REM For a database server on a separate host:
REM (a) Use this command for a database on remote server. Add other options if need be.
REM > mysqldump --opt --protocol=TCP --user=%USER% --password=%PASS% --host=%DBSERVER% %DATABASE% > %OUTPUT%
REM 
REM (b) Use this command for a database server on localhost. Add other options if need be.
mysqldump.exe -u%USER% -p%PASS% --single-transaction --skip-lock-tables --routines --triggers %DATABASE% > %OUTPUT%
