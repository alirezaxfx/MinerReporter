import huaweisms.api.user
import huaweisms.api.sms

import hashlib
import binascii
import json
import huaweisms.api.webserver
import sys

from huaweisms.api.common import common_headers, ApiCtx, post_to_url, get_from_url

def quick_login2(username: str, password: str, modem_host: str = None):
    ctx = ApiCtx(modem_host=modem_host)
    token = huaweisms.api.webserver.get_session_token_info(ctx.api_base_url)
    #print (token)
    ctx.session_id = token['response']['SesInfo']
    ctx.login_token = token['response']['TokInfo']
    response = huaweisms.api.user.login(ctx, username, password)
    if not ctx.logged_in:
        raise ValueError(json.dumps(response))
    return ctx


print ('Send SMS to %s Content:\'%s\'\n' % (sys.argv[1], sys.argv[2]))

ctx = quick_login2("admin", "alireza1368", modem_host='192.168.5.2')
#print(ctx)
# sending sms
huaweisms.api.sms.send_sms( ctx, sys.argv[1], sys.argv[2] )

