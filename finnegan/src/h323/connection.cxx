#include <ptlib.h>
#include "connection.h"

static MyConnection *connection;

void connSignalHandler(int sig)
{
	switch(sig) {
	        case SIGALRM:
			connection->ClearCall();
	                FCH::exitCode = 4;
	                FCH::terminationSync.Signal();
	                break;
	}
}


MyConnection::MyConnection(const ProgConf & conf, H323EndPoint & endpoint, unsigned callReference) : H323Connection::H323Connection(endpoint, callReference), progConf(conf)
{
	connection = this;
	PTRACE(1, "connection constructor");
	signal(SIGALRM, connSignalHandler);
	alarm(progConf.timeout);
}


MyConnection::~MyConnection() 
{
	PTRACE(1, "connection destructor");
	alarm(0);
	signal(SIGALRM, SIG_IGN);
}

void MyConnection::OnEstablished()
{
	PTRACE(1, "connection established");
	alarm(0);
	signal(SIGALRM, SIG_IGN);
}

void MyConnection::OnUserInputIndication(const H245_UserInputIndication & pdu)
{
	PTRACE(1, "User input indication - pretending its DTMF");

	MyEndPoint *ep = (MyEndPoint *) &endpoint;

	ep->gotDTMF = TRUE;
	ep->wavchannel->openWavFile(PFilePath(progConf.snoozeFile), PFile::ReadOnly, FALSE);
}

BOOL MyConnection::OnReceivedAlerting(const H323SignalPDU & pdu)
{
	 PString se, de;

	PTRACE(1, "Source alias: " << alertingPDU->GetSourceAliases(NULL));
	PTRACE(1, "Dest alias: " << alertingPDU->GetDestinationAlias(FALSE));

	if(alertingPDU->GetSourceE164(se))
		PTRACE(1, "Source E164: " << se);
	else
		PTRACE(1, "No source E164.");

	if(alertingPDU->GetDestinationE164(de))
		PTRACE(1, "Dest E164: " << de);
	else
		PTRACE(1, "No dest E164.");

        return TRUE;
}
