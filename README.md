# User usage report

To generate the report for a user, run the following command:

```
$ sudo -u www-data ./occ usage-report:generate admin
"admin","2017-09-18T09:00:01+00:00",5368709120,786432000,12,1,1,2
```

Leaving out the user argument will generate a report for all users on the system:

```
$ sudo -u www-data ./occ usage-report:generate --display-name
"admin","Nextcloud Admin","2017-09-18T09:00:01+00:00",5368709120,786432000,12,1,1,2
"test1","Test User 1","2017-09-18T09:00:01+00:00",-2,954368,6,0,2,10
"test2","Second Test user","2017-09-18T09:00:01+00:00",-2,164,4,0,0,0
"test3","Test User Three","2017-09-18T09:00:01+00:00",-2,164,4,0,0,0
"test5","Fifth Tester","2017-09-18T09:00:01+00:00",-2,164,4,0,0,0
```

The CSV data is the following:

* User identifier
* User display name (when `--display-name` is given)
* Current date and time (default in ISO 8601 format, but any format can be specified)
* Last login date and time (default in ISO 8601 format, but any format can be specified)  (when `--last-login` is given)
* Assigned home storage size in bytes (`-3` is unlimited, `-2` is unknown/not set)
* Disk space consumed by home storage in bytes (`-2` is unknown)
* Number of files in home storage
* Number of shares created
* Number of files created (new files only)
* Number of files read (download/view)

