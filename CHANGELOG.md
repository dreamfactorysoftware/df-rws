# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added

### Changed

### Fixed

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

[Unreleased]: https://github.com/dreamfactorysoftware/df-rws/compare/0.5.0...HEAD
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
