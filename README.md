Simple Silex based application to search in literature references imported from BibTex files hosted on pagodabox.

### Pagadobox Environment Variables 
If your are not using pagodabox you have to set these variables
- DB1_HOST  mysql host (standard localhost)
- DB1_PORT  mysql port (standard 3306)
- DB1_NAME  mysql database name
- DB1_USER  mysql user
- DB1_PASS  mysql passsword

### Additionals Pagadobox Environment Variables 
These variables have to been set
- APP_DEBUG true or false
- APP_USER user name
- APP_PASSWORD encrypted password

## Usage
/import/reinstall   initialize the database
/import    install a new reference list
/import/remove/{name}   remove all data from the reference list (= {name})