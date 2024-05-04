#! /bin/bash

set -e 

shell=$(ps w | grep -E "^\s{0,}$$" | basename `awk ' { print (substr($5, 1, 1) == "{" ? $6 : $5) } '`)

[ "$shell" != bash ] && \
	echo "email.sh: please respect my shebang!" && \
	exit 0

scriptDir=`dirname $BASH_SOURCE`

. $scriptDir/tools/email.sh

user=$(uname)
revAName="/etc/ssmtp/revaliases"
revA=$(grep -E "^$user" $revAName | \
	awk -F ":" ' { print $2" "$3($4 != "" ? ":"$4 : "") } ') && \
	__from=${revA// */} && __smtpServer=${revA//* /}

[ -z "$__smtpServer" ] && echo "configure your mail server in $revAName for user $user" && \
	exit 1

# overide revalias if needed
# __from="admin@$(hostname -l)"

__rcpt="" && [ $# -ge 1 -a -n "$1" ] && __rcpt="$1"

__generateEmail gpxlog.gpx $__from $__rcpt $__smtpServer

exit 0
