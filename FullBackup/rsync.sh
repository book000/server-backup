#/bin/bash
SCRIPT_DIR=$(cd $(dirname $0); pwd)

usage_exit() {
    echo "Usage: $0 [-h host] [-r port] [-u user] [-i identity] [-p passphrase] [-f from] [-o output]" 1>&2
    exit 1
}

while getopts h:r:u:i:p:f:o: OPT
do
    case $OPT in
        h)  HOSTNAME=$OPTARG
            ;;
        r)  PORT=$OPTARG
            ;;
        u)  USERNAME=$OPTARG
            ;;
        i)  IDENTITY=$OPTARG
            ;;
        p)  PASSPHRASE=$OPTARG
            ;;
        f)  FROM=$OPTARG
            ;;
        o)  OUTPUT=$OPTARG
            ;;
        \?) usage_exit
            ;;
    esac
done
LOGPATH="$SCRIPT_DIR/rsync.log"
TODAY=$(date +%Y%m%d)

SSHCMD="rsync -arhvz --progress --delete --backup --exclude-from='${SCRIPT_DIR}ignore' -e 'ssh -p $PORT -i $IDENTITY' --rsync-path='sudo rsync' --backup-dir="${OUTPUT}$TODAY" $USERNAME@$HOSTNAME:$FROM ${OUTPUT}latest 2>&1 | tee $LOGPATH"
expect -c "
    set timeout 30
    spawn sh -c \"$SSHCMD\"

    expect ":"

    send \"$PASSPHRASE\n\"

    interact
    "
