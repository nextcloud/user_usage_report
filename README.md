# User usage report

To generate the report for a user, run the following command:

```
$ sudo -u www-data ./occ usage-report:generate admin
"admin","2017-09-18T09:00:01+00:00",5368709120,786432000,12,1,1,2
```

Leaving out the user argument will generate a report for all users on the system:

```
$ sudo -u www-data ./occ usage-report:generate
"admin","2017-09-18T09:00:01+00:00",5368709120,786432000,12,1,1,2
"test1","2017-09-18T09:00:01+00:00",-2,954368,6,0,2,10
"test2","2017-09-18T09:00:01+00:00",-2,164,4,0,0,0
"test3","2017-09-18T09:00:01+00:00",-2,164,4,0,0,0
"test5","2017-09-18T09:00:01+00:00",-2,164,4,0,0,0
```

The CVS data is the following:

* User identifier
* Date and time (default in ISO 8601 format, but any format can be specified)
* Assigned home storage size in bytes (`-3` is unlimited, `-2` is unknown/not set)
* Disk space consumed by home storage in bytes (`-2` is unknown)
* Number of files in home storage
* Number of shares created
* Number of files created (new files only)
* Number of files read (download/view)
