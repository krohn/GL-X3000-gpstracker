modemDev=/dev/mhi_DUN

modemErr="+CME ERROR:"

modemCmd () {
	modemErrorCode=""
	
	grepS=${2/+/\\+}		# escape "+"
	grepE=${modemErr/+/\\+}	# escape "+"
	awkF="${2/+/\\\\+}" 	# escape "+"
	result=`echo "$1" | socat - $modemDev,crnl | grep -E "($grepS|$grepE)" | awk -F "$awkF" ' { print $(NF) } '`

	echo $result
}

modemError () {
	errCode=""
	awkF="${modemErr/+/\\\\+}" 	# escape "+"
	[[ "$1" = "$modemErr*" ]] && \
		errCode=`echo $1 | awk -F "$awkF" ' { print $(NF) } '`
	
	echo $errCode
}