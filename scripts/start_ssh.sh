#!/bin/bash
while [ 1 ]; do
	ssh -p 2123 -o ExitOnForwardFailure=yes -o ServerAliveInterval=60 -N -T -i /home/pi/.ssh/id_rsa -R2233:localhost:22 root@tu.mahtabpcef.com
done
