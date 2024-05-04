#!/bin/bash
set -e

shell=$(ps w | grep -E "^\s{0,}$$" | basename `awk ' { print (substr($5, 1, 1) == "{" ? $6 : $5) } '`)

[ "$shell" != bash ] && \
	echo "sms-check.sh: please respect my shebang!" && \
	exit 0

if [ -z $_SMS_CHECK_SH ]; then
	_SMS_CHECK_SH=1

	scriptDir=`dirname $BASH_SOURCE`
	
	source $scriptDir/modem.sh

	validMasters=""

	# reading masters
	[ -f $scriptDir/sms-check.masters ] && \
		validMasters="`awk ' { if (match($0, /^#/) > 0) next; print $1 } ' $scriptDir/sms-check.masters 2> /dev/null | xargs`"

	smsBaseDir=/etc/spool/sms
	inBox=incoming
	stBox=storage
	outBox=outgoing

	boxName () {
		name=`find $smsBaseDir/$1 -type d -mindepth 1`
		echo ${name//$smsBaseDir\//}
	}

	checkDir () {
		for file in `find $smsBaseDir/$1 -type f -mtime -7`; do
			fName=`basename ${file//$smsBaseDir\//}`
			relDir=`dirname ${file//$smsBaseDir\//}`
			checkFile $relDir $fName
		done
	}

	checkFile () {
		fName=$smsBaseDir/$1/$2
		from=`grep -iE "^From: " $fName | awk -F " " ' { print $(NF) } '`
		
		# check from
		( ! [[ "$validMasters" =~ ( |^)$from( |$) ]] ) && return

		len=`grep -iE "^Length: " $fName | awk -F " " ' { print $(NF) } '`
		line=`grep -n -E "^$" $fName | awk -F ":" ' { print $1+1 } '`
		content=`tail -n +$line $fName`

		if [ "$content" == "Report GPS-Position" ]; then
			echo -n "file: $2 in $1 "

			[ -d $smsBaseDir/$stBoxName -a ! -f $smsBaseDir/$stBoxName/$2 ] && \
				mv $fName $smsBaseDir/$stBoxName || \
				return

			gps=$(modemCmd 'AT+QGPSLOC=2' '+QGPSLOC:') 
			
			if [ ! -z "$gps" -a ${#gps} -le 100 -a -d $smsBaseDir/$outBoxName ]; then
				echo -n "reporting gps position to $from " && \
					logger -t "sms-check.sh" "reporting gps position to $from " && \
					echo -e "To: $from\n\ngps position: "`date +"%d.%m.%Y %H:%M.%S"`" $gps" > /tmp/$2_response && \
					mv /tmp/$2_response $smsBaseDir/$outBoxName/ && \
					echo " ok" || echo " failed"
			fi
		fi
		if [ "$content" == "Upload GPX-Track" ]; then
			echo -n "file: $2 in $1 "

			[ -d $smsBaseDir/$stBoxName -a ! -f $smsBaseDir/$stBoxName/$2 ] && \
				mv $fName $smsBaseDir/$stBoxName || \
				return

			echo -n "upload gpx-track requested from $from " && \
				logger -t "sms-check.sh" "upload gpx-track requested from $from " && \
				[[ $(type -t forceGpxTrackUpload) == function ]] && \
				forceGpxTrackUpload "sms request to force upload" && \
				echo " ok" || echo " failed"
		fi
		if [[ "$content" == "Mail GPX-Track" ]]; then
			echo -n "file: $2 in $1 "

			email="`awk ' { if(match($0, /^'$from'[[:space:]]/) > 0) print $2 } ' $scriptDir/sms-check.masters`"
			
			[ -z "$email" ] && echo "no email address configured" && return

			[ -d $smsBaseDir/$stBoxName -a ! -f $smsBaseDir/$stBoxName/$2 ] && \
				mv $fName $smsBaseDir/$stBoxName || \
				return

			echo -n "mail gpx-track requested from $from " && \
				logger -t "sms-check.sh" "mail gpx-track requested from $from " && \
				[[ $(type -t mailGpxTrack) == function ]] && \
				mailGpxTrack "$email" && \
				echo " ok" || echo " failed"
		fi
	}

	if [ -n "$validMasters" ]; then
		inBoxName=$(boxName "$inBox")
		stBoxName=$(boxName "$stBox")
		outBoxName=$(boxName "$outBox")

		# echo "inBox: $inBoxName, st: $stBoxName, out: $outBoxName"

		checkDir $inBoxName
	fi

# _SMS_CHECK_SH
fi