#! /bin/bash
set -e

gpxLogDir=/opt/gpstracker/gps/
gpxLogFile=gpxlog.gpx

# callback for sms-check.sh
forceGpxTrackUpload() {
	if [ -n "$1" ]; then
		$(echo -e "<!-- $1 -->" >> $gpxLogDir$gpxLogFile) && \
		[ -x `dirname $0`/gpx-upload.php ] && \
		`dirname $0`/gpx-upload.php && \
		echo -n "ok"
	else 
		echo -n "error in forceGpxTrackUpload()"
	fi
}
mailGpxTrack() {
	if [ -n "$1" ]; then
		`dirname $0`/email.sh "$1" && \
		echo -n "ok"
	else 
		echo -n "error in mailGpxTrack()"
	fi
}

# run sms-checks
source `dirname $0`/tools/sms-check.sh

# gpxlogger & gpsd
scriptName=`basename $0`
pidCmd=/bin/pidof
chmCmd=/bin/chmod
chgCmd=/bin/chgrp
logCmd=/usr/bin/logger
gpsdCmd=/usr/sbin/gpsd
gpxCmd=/usr/bin/gpxlogger
gpxOpts='-d -i 60 -m 20 -r'

writeLog() {
	$logCmd -t $scriptName "$1"
	echo $(date +"%d.%m.%Y %H:%M.%S %Z")": $1" >&2;
}

getPidof() {
	echo `$pidCmd \`basename $1\``
}

startGpxLogger () {
	writeLog "Starting `basename $gpxCmd`"
	
	$gpxCmd $gpxOpts -f $gpxLogDir$gpxLogFile localhost
	gpxLoggerPid=$(getPidof $gpxCmd)
}

gpsdPid=$(getPidof $gpsdCmd)
gpxLoggerPid=$(getPidof $gpxCmd)

if [ -n "$gpsdPid" -a -n "$gpxLoggerPid" ]; then
	if [[ /var/run/gpsd.pid -nt $gpxLogDir$gpxLogFile ]]; then
		writeLog "gpsd has been restarted while gpxlogger runs. Forcing restart"
		kill $gpxLoggerPid && gpxLoggerPid=""
	fi
fi

if [ ! -z "$gpsdPid" -a -z "$gpxLoggerPid" ]; then
	if [ -f $gpxLogDir$gpxLogFile ]; then
		mvName="${gpxLogFile/\.*/}_`date +"%Y-%m-%d_%H-%M-%S" -r $gpxLogDir$gpxLogFile`.${gpxLogFile##*.}"
		writeLog "Renaming $gpxLogFile to $mvName"
		mv $gpxLogDir$gpxLogFile $gpxLogDir$mvName
		$chmCmd 440 $gpxLogDir$mvName
		$chgCmd www-data $gpxLogDir$mvName
	fi
	startGpxLogger
fi

logName="/var/log/${scriptName/\.*/.log}"
if [ -f $logName ]; then
	wcl=$(wc -l $logName | awk ' { print $1 } ')
	if [ $wcl -ge 100 ]; then
		# writeLog "shrinking $logName" && \
		log="$(tail -n 100 $logName)" && \
		echo -e "$log" > $logName || \
		writeLog "shrinking $logName failed"
	fi
fi