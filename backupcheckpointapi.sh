#!/bin/bash

# Database credentials
# dbuser="hdde4090_checkpointprod"
# dbpass="Init123.*#"
# dbname="hdde4090_dbcheckpointprod"

dbuser="hdde4090_checkpointapi"
dbpass="Init123.*#"
dbname="hdde4090_dbcheckpointapi"

# Output filename with timestamp
datetime=$(date +"%Y%m%d_%H%M%S")

# Output filename (adjust path as needed)
filename="${dbname}_${datetime}"

echo "File Name: $filename"

# Dump only the 'attends' table from the database
mysqldump --quick --skip-extended-insert --hex-blob --verbose -u "$dbuser" -p"$dbpass" "$dbname" > "${filename}.sql"

# Compress the SQL file using zip
zip "${filename}.zip" "${filename}.sql"

# Remove the raw SQL file
rm "${filename}.sql"