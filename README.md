# User usage report

To generate the report for a user, run the following command:

```
$ sudo -u www-data ./occ usage-report:generate admin
"admin","2017-09-18T09:00:01+00:00","6 MB","1.1 MB",12,1,1,2
```

Leaving out the user argument will generate a report for all users on the system:

```
$ sudo -u www-data ./occ usage-report:generate
"admin","2017-09-18T09:00:01+00:00","6 MB","1.1 MB",12,1,1,2
"test1","2017-09-18T09:00:01+00:00",-2,"932 KB",6,0,2,10
"test2","2017-09-18T09:00:01+00:00",-2,"164 B",4,0,0,0
"test3","2017-09-18T09:00:01+00:00",-2,"164 B",4,0,0,0
"test5","2017-09-18T09:00:01+00:00",-2,"164 B",4,0,0,0
```

The CVS data is the following:

* User identifier
* Date and time (default in ISO 8601 format, but any format can be specified)
* Assigned home storage size (`-3` is unlimited, `-2` is unknown/not set)
* Disk space consumed by home storage
* Number of files in home storage
* Number of shares created
* Number of files created (new files only)
* Number of files read (download/view)
