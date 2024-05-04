#! /bin/sh

#set -e

. `dirname $BASH_SOURCE`/utils.sh

# parameters
#
# 1     GPX-filename
# 2		from
# 3		optional recipient (used if no certs available)
# 4		optional smtpServer (used if no certs available)
__generateEmail()
{
	[ $# -ge 3 -a -n "$3" -a -z "$__rcpt" ] && __rcpt="$3"
	[ $# -ge 4 -a -n "$4" ] && smtp="$4"

	__logData=$(wget -q http://$(hostname -l)/gps/?f=$1 -O - )

	__cerPath=$scriptDir/certs/
	__sub="GPX-Log"
	# recipte
	[ -d $__cerPath ] && \
	for cert in ${__cerPath}*.cer; do
		[ "`basename $cert`" == "*.cer" ] && break
		__rcpt=$(cerEmail "$cert")
		__certRcpt="$cert"
		break
	done
	# signer
	if [ -f "${__cerPath}signer.pem" -a -f "${__cerPath}signer_key.pem" ]; then
		__signer="${__cerPath}signer.pem"
		__signer_key="${__cerPath}signer_key.pem"
	fi
	
	[ -z "$__rcpt" ] && echo "no recipient found in certs/given by parameter" && exit 1

	__logData=$(unix2dos "$__logData")
	__logData_base64=$(base64 "$__logData")

	__boundry="=----------"`date +"Date%d%m%YTime%H%M%S"`"Pid$$"
	__header="\
FROM: ${__from}\n\
TO: ${__rcpt}\n\
SUBJECT: ${__sub}\n\
"

	__msg="\
MIME-Version: 1.0\n\
Content-Type: multipart/mixed;\n\
 boundary=\"${__boundry}\"\n\
\n\
This is a multi-part message in MIME format.\n\
\n\
--${__boundry}\n\
Content-Type: text/plain; charset=iso-8859-15\n\
Content-Transfer-Encoding: quoted-printable\n\
\n\
${__sub}\n\
\n\
${__traff_base64}\n\
\n\
--${__boundry}\n\
Content-Type: application/octet-stream;\n\
 name=\"$1\"\n\
Content-Disposition: attachment;\n\
 filename=\"$1\"\n\
Content-Transfer-Encoding: base64\n\
\n\
${__logData_base64}\n\
\n\
--${__boundry}--\n\
\n\
"

	__header=$(unix2dos "$__header")
	__msg=$(unix2dos "$__msg")

	msgFileName="${1//.gz/.msg_to_send}"
	echo -e "$__msg" > $msgFileName

	msgSignedName=""
	if [ -n "$__signer" -a -n "$__signer_key" ]; then
		msgSignedName="${msgFileName}_signed"
		smimeSign "$__signer" "$__signer_key" "$msgFileName" "$msgSignedName"
	fi

	if [ -n "$__certRcpt" ]; then
		msgEncryptedName="${msgFileName}_encrypted"
		tmpFileName="$msgFileName"
		[ -n "$msgSignedName" ] && tmpFileName="$msgSignedName"
		smimeEncrypt "$__certRcpt" "$__from" "$__sub" "$tmpFileName" "$msgEncryptedName"
		__header=""
		__sendMail "$msgEncryptedName"

		[ -n "$msgEncryptedName" -a -f "$msgEncryptedName" ] && rm -rf "$msgEncryptedName"
		[ -n "$msgSignedName" -a -f "$msgSignedName" ] && rm -rf "$msgSignedName"
		[ -n "$msgFileName" -a -f "$msgFileName" ] && rm -rf "$msgFileName"
		return 0
	fi

	# insert header if not empty
	[ -n "$__header" ] && __smtp="${__header}\n"
	__smtp="${__smtp}"`cat "$msgFileName"`
	__smtp=$(dos2unix "$__smtp")
	__smtp=$(unix2dos "$__smtp")

	echo -e "$__smtp" > $msgFileName
	
	__sendMail "$msgFileName" "$__smtp"
	[ -n "$msgFileName" -a -f "$msgFileName" ] && rm -rf "$msgFileName"
}

# parameters
#
# filename with msg
# smpt server
__sendMail()
{
	if [ -f "$1" ]; then
		progress=${1}_in_progress
		mv $1 $progress
		
		# read sender
		__from=`grep -ie "^FROM: " $progress | awk -F":\ " ' { print $2 } '`
		__from=$(dos2unix "$__from")
		
		# read recipient
		__rcpt=`grep -ie "^TO: " $progress | awk -F":\ " ' { print $2 } '`
		__rcpt=$(dos2unix "$__rcpt")

		# extract server
		[ $# -ge 2 -a -n "$2" ] && __srv="$2" || __srv=${__rcpt#*@}

		# sending email
#		sendmail -f $__from -S $__srv -t < "$progress" && sendMailError=0 || sendMailError=1
		sendmail -f $__from -t < "$progress" && sendMailError=0 || sendMailError=1
	
#		if [ $? -eq 0 ]; then
		if [ $sendMailError -eq 0 ]; then
			rm -f "$progress"
		else
			mv $progress $1
		fi
	fi
}
