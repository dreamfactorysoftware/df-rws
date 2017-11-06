# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
## [0.13.1] - 2017-11-06
- Tolerate array formatted URL parameters better

## [0.13.0] - 2017-11-03
- Upgrade Swagger to OpenAPI 3.0 specification

## [0.12.1] - 2017-10-04
### Fixed
- Handled Restlet transfer encoding lower case

## [0.12.0] - 2017-08-17
### Changed
- Reworked API doc usage and generation

## [0.11.1] - 2017-07-31
- DF-1173 Drop transfer-encoding header in cURL response if chunked

## [0.11.0] - 2017-07-27
- DF-1130 Use config member variable that is now updated with session lookups

## [0.10.0] - 2017-06-05
### Changed
- Cleanup - removal of php-utils dependency
- Look for headers from request to pass through when called from scripting environment

## [0.9.0] - 2017-04-21
### Changed
- Use new service config handling for database configuration
- DF-756 Detected and replaced external links in rws response with DF api links

## [0.8.0] - 2017-03-03
- Major restructuring to upgrade to Laravel 5.4 and be more dynamically available

## [0.7.1] - 2017-02-15
### Fixed
- Set cURL response headers in response to client

## [0.7.0] - 2017-01-16
### Fixed
- Undefined index: QUERY_STRING error when using built in php server
- No param passing issue (empty QUERY_STRING) when calling RWS from a scripted service

## [0.6.0] - 2016-11-17
### Changed
- Use null for empty service doc

## [0.5.0] - 2016-10-03
### Changed
- DF-826 Updating to latest df-core models.

## [0.4.3] - 2016-09-23
### Fixed
- DF-878 When performing a GET on base of the service with 'as_access_list' you now get documented paths.

## [0.4.2] - 2016-09-06
### Fixed
- Fixed a bug where adding a query string without equal sign (=) from client would break the remote call.

## [0.4.1] - 2016-08-23
### Fixed
- DF-854 Allowing pass-thru of repeated query parameters in remote web service.

## [0.4.0] - 2016-08-21
### Changed
- General cleanup from declaration changes in df-core for service doc and providers

### Fixed
- Fix path for event
- Issue #99. Need local copy of options for clean processing.
- DF-779 Now supports PHP defines as CURLOPT_ values, i.e. use in the CURLAUTH_ANY case.
- DF-681 Event firing changes for resources.

## [0.3.1] - 2016-07-08
### Changed
- General cleanup from declaration changes in df-core.
- Clear API doc for remote web service, use user defined doc
- DF-676 Adding event matching from swagger documentation paths to support event firing on exact and matching paths.
- DF-798 Needed to urlencode resources forwarded on to remote

## [0.3.0] - 2016-05-27
### Changed
- Moved seeding functionality to service provider to adhere to df-core changes.

## [0.2.2] - 2016-02-09
### Fixed
- Fix headers getting dropped

## [0.2.1] - 2016-01-29
### Fixed
- Error on null options config on a new service.

## [0.2.0] - 2016-01-29
### Added
- Now supports scripting events based on swagger definition.
- Now supports CURLOPT_XXX configurations.

### Changed
- **MAJOR** Updated code base to use OpenAPI (fka Swagger) Specification 2.0 from 1.2

### Fixed

## [0.1.1] - 2015-11-20
### Fixed
- Fixed internal logic to use ColumnSchema from df-core instead of arrays.
- Fixed reported record creation issue.

## 0.1.0 - 2015-10-24
First official release working with the new [df-core](https://github.com/dreamfactorysoftware/df-core) library.

[Unreleased]: https://github.com/dreamfactorysoftware/df-rws/compare/0.13.1...HEAD
[0.13.1]: https://github.com/dreamfactorysoftware/df-rws/compare/0.13.0...0.13.1
[0.13.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.12.1...0.13.0
[0.12.1]: https://github.com/dreamfactorysoftware/df-rws/compare/0.12.0...0.12.1
[0.12.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.11.1...0.12.0
[0.11.1]: https://github.com/dreamfactorysoftware/df-rws/compare/0.11.0...0.11.1
[0.11.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.10.0...0.11.0
[0.10.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.9.0...0.10.0
[0.9.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.8.0...0.9.0
[0.8.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.7.1...0.8.0
[0.7.1]: https://github.com/dreamfactorysoftware/df-rws/compare/0.7.0...0.7.1
[0.7.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.4.3...0.5.0
[0.4.3]: https://github.com/dreamfactorysoftware/df-rws/compare/0.4.2...0.4.3
[0.4.2]: https://github.com/dreamfactorysoftware/df-rws/compare/0.4.1...0.4.2
[0.4.1]: https://github.com/dreamfactorysoftware/df-rws/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.3.1...0.4.0
[0.3.1]: https://github.com/dreamfactorysoftware/df-rws/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.2.2...0.3.0
[0.2.2]: https://github.com/dreamfactorysoftware/df-rws/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/dreamfactorysoftware/df-rws/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/dreamfactorysoftware/df-rws/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/dreamfactorysoftware/df-rws/compare/0.1.0...0.1.1
