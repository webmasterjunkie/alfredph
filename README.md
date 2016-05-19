# Alfred App : Performance Horizon API

This workflow will allow you to create hotkey commands to pull down CSV files using the API from [Performance Horizon](http://apidocs.performancehorizon.com/index.php?title=API_Documentation).

## Install

Download the ZIP file and import by double clicking.

## Usage

The first things you need to run is:

```
ph auth
```

This will allow you to set up your credentials. You can then run:

```
ph report
```

This will allow you to configure basic (at this time) settings for the API call. Once you are complete, it will allow you to download
it automatically. You can re-download the file any time by running:

```
ph download
```

Since this uses contextual dates it really leverages the power of being able to get refreshed data instantly.

## Requirements

+ Performance Horizon AUTH
+ Alfred 3
  + Will work with Alfred 2, but will require changes to scripting
+ Powerpack

### Nice to Have

+ Growl
