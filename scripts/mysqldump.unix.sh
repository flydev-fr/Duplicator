#!/bin/sh

# (1) Set up all the mysqldump variables
FILE=%%FILE%%
DBSERVER=%%SERVER%%
PORT=%%PORT%%
DATABASE=%%DATABASE%%
USER=%%USER%%
PASS=%%PASS%%
CACHEPATH=%%CACHEPATH%%

# Fix trailing slash in cache path
CACHEPATH="${CACHEPATH%/}/"

OUTPUT="${CACHEPATH}${FILE}"

# (2) In case you run this more than once a day, remove the previous version of the file
# unalias rm     2> /dev/null
# rm ${FILE}     2> /dev/null
# rm ${FILE}.zip  2> /dev/null

# (3) Do the mysql database backup (dump)
#  - to log errors of the dump process to a file, add --log-error=mysqldump_dup_error.log
# (a) Use this command for a remote database. Add other options if need be.
# mysqldump --opt --protocol=TCP --user=${USER} --password=${PASS} --host=${DBSERVER} --port=${PORT} ${DATABASE} > ${OUTPUT}
# (b) Use this command for a database server on localhost. Add other options if need be.
mysqldump --routines --triggers --single-transaction --user=${USER} --password=${PASS} --databases ${DATABASE} > ${OUTPUT}
