#!/bin/bash
set -e

dos2unix()
{
        d2u=`echo -ne "$1" |  awk ' { printf "%s\n", (substr($0, length($0)) == "\r" ? substr($0, 1, length($0)-1) : $0) } '`
        echo -n "$d2u"
}

unix2dos()
{
        u2d="`echo -ne "$1" | awk ' { printf "%s\r\n", $0 } '`"
        echo -n "$u2d"
}

base64()
{
	opt="-e"
	
	if [ $# -lt 1 ]; then
		return 0
	fi
	
	if [ $# -ge 2 -a "$2" == "-d" ]; then
		opt="-d"
	fi
	
	b64=`echo -e "$1" | openssl enc $opt -a`
	
	echo "$b64"
}

smimeSign()
{
	if [ $# -lt 4 ]; then
		return 1 
	fi

	openssl smime -sign -signer $1 -inkey $2 -in $3 -out $4	
}

smimeEncrypt()
{
	if [ $# -lt 5 ]; then
		return 1
	fi

	email=$(cerEmail "$1")
	openssl smime -encrypt -des3 -from "$2" -to "$email" -subject "$3" -in "$4" -out "$5" "$1"
}

cerEmail()
{
	res=$(openssl x509 -in "$1" -noout -email) && echo $res || echo ""
}

uname() {
	echo $(id | awk ' { print gensub(/^uid=([0-9]+)\(([[:alpha:]]+)\)/, "\\2", "g", $1 ) } ')
}

hostname() {
	[ $# -ge 1 -a "$1" == "-l" ] && \
		host=`uci get dhcp.@domain[0].name`
	[ -z "$host" ] && host=`uci get system.@system[0].hostname`
	echo "$host"
}
