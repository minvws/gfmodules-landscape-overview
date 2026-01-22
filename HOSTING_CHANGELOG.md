# Hosting changelog

## NEXT FUTURE RELEASE 

### Added

- ENV: `BASIC_AUTH_MGO_USERNAME` and `BASIC_AUTH_MGO_PASSWORD` (optional)  
  These keys are referenced in mgo-services.json to enable basic authentication for MGO projects.

- ENV: `SERVICES_FILE` (optional)  
  Path/filename of the services configuration file to use. Defaults to `services.json`.  
  Should be used to point to `mgo-services.json` for MGO project

- ENV: `APP_NAME` (optional)  
  Overrides the displayed application name. Defaults to **GFModules Overview**.
