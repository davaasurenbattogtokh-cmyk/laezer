#!/usr/bin/env python3
"""HTTP smoke tests for a DISPOSABLE local PHP server, never a live host.

Server fixture: empty *_test MySQL database, origin=https://laezer.shop,
setup_key='test-http-install-key-' repeated 3 times, notifications disabled.
Emulate HTTPS only in the external test router; never ship that router.
"""
from pathlib import Path
import base64
import http.client
import json
import os
from urllib.parse import urlencode, urlparse

if os.environ.get('LAEZER_HTTP_TEST_ONLY') != '1':
    raise SystemExit('Set LAEZER_HTTP_TEST_ONLY=1 for a disposable local fixture.')
url = urlparse(os.environ['LAEZER_HTTP_TEST_URL'])
if url.scheme != 'http' or url.hostname != '127.0.0.1' or not url.port:
    raise SystemExit('Only an explicit 127.0.0.1 test port is permitted.')
count = 0
def request(path, method='GET', payload=None, token='', origin='https://laezer.shop', extra=None, host='laezer.shop'):
    conn = http.client.HTTPConnection('127.0.0.1', url.port, timeout=15)
    headers = {'Host': host, 'Origin': origin}
    if token: headers['Cookie'] = '__Host-laezer_session=' + token
    if isinstance(payload, dict):
        payload = json.dumps(payload, ensure_ascii=False).encode()
        headers['Content-Type'] = 'application/json'
    if extra: headers.update(extra)
    conn.request(method, path, payload, headers)
    r = conn.getresponse(); data = r.read(); h = dict(r.getheaders()); status = r.status; conn.close()
    parsed = json.loads(data) if h.get('Content-Type', '').startswith('application/json') else data
    return status, parsed, h
def check(value, name):
    global count
    assert value, name
    count += 1
    print('PASS HTTP ' + name, flush=True)
def login(name, password, role):
    status, data, headers = request('/api/auth/login', 'POST', {'username': name, 'password': password, 'role': 'admin'})
    check(status == 200 and data['actor']['role'] == role, 'shared login detects ' + role)
    cookie = headers['Set-Cookie']; check(all(flag.lower() in cookie.lower() for flag in ['Secure','HttpOnly','SameSite=Strict','path=/']), 'secure session cookie flags')
    return cookie.split(';')[0].split('=', 1)[1]

check(request('/setup')[0] == 200, 'installer available before setup')
form = urlencode({'setup_key': 'test-http-install-key-'*3, 'username': 'admin', 'password': 'HttpFixtureAdmin!Only', 'confirm': 'HttpFixtureAdmin!Only'}).encode()
s, d, h = request('/setup', 'POST', form, extra={'Content-Type': 'application/x-www-form-urlencoded'})
check(s == 200 and 'Суулгалт дууслаа'.encode() in d, 'one-time installation creates schema and admin')
check(request('/setup')[0] == 303, 'installer locks after success')
check(request('/admin')[0] == 303, 'private pages redirect unauthenticated users')
check(request('/api/portal/admin/products')[0] == 401, 'admin API requires login')
check(request('/api/auth/login','POST',{'username':'admin','password':'wrong'})[0] == 401, 'incorrect password rejected')
check(request('/api/auth/login','POST',{},origin='https://evil.test')[0] == 403, 'cross-origin login rejected')
check(request('/',host='evil.test')[0] == 421, 'unconfigured host rejected')
check(request('/',host='www.laezer.shop')[0] == 308, 'www redirects to canonical domain')
admin = login('admin','HttpFixtureAdmin!Only','admin')
check(request('/admin',token=admin)[0] == 200, 'admin page available with session')
_, data, _ = request('/api/portal/admin/products',token=admin)
p = data['products'][0]; p['resellerPrice'] = 80000
check(request('/api/portal/admin/products','POST',p,admin)[0] == 200, 'admin can set wholesale price')
account = {'name':'HTTP Partner','email':'http-partner@example.test','phone':'99999999','username':'http-partner','password':'HttpFixturePartner!Only','active':True}
check(request('/api/portal/admin/accounts','POST',account,admin)[0] == 200, 'admin creates reseller credentials')
partner = login('http-partner','HttpFixturePartner!Only','reseller')
check(request('/api/portal/admin/products',token=partner)[0] == 403, 'reseller cannot read admin endpoint')
_, d, _ = request('/api/catalog'); check(d['products'][0]['price'] == 100000 and 'resellerPrice' not in d['products'][0], 'public catalog hides wholesale prices')
_, d, _ = request('/api/portal/catalog',token=partner); check(d['products'][0]['price'] == 80000, 'reseller catalog uses private price')
order = {'name':'HTTP Test Buyer','phone':'99999999','address':'Ulaanbaatar HTTP fixture','payment':'bank','items':[{'sku':'LZ-001','qty':1}],'expectedTotal':105000}
s, d, _ = request('/api/orders','POST',order,extra={'Idempotency-Key':'http-retail-order-001'}); receipt = d['order']
check(s == 201 and receipt['total'] == 105000, 'retail order stored at canonical price')
_, d, _ = request('/api/orders','POST',order,extra={'Idempotency-Key':'http-retail-order-001'}); check(d['order']['id'] == receipt['id'], 'retry returns same receipt')
check(request('/api/portal/orders','POST',{**order,'expectedTotal':85000},partner,extra={'Idempotency-Key':'http-partner-order-001'})[0] == 201, 'reseller order stored at wholesale price')
_, d, _ = request('/api/portal/orders?channel=reseller',token=admin); check(d['count'] == 1 and d['orders'][0]['channel'] == 'reseller','admin sees separate reseller orders')
check(request('/api/portal/admin/status','POST',{'id':receipt['id'],'status':'shipped'},admin)[0] == 200, 'admin changes fulfillment status')
_, d, _ = request('/api/portal/admin/banners',token=admin); banner = d['banners'][0]; banner['title']='HTTP <script>fixture</script>'
check(request('/api/portal/admin/banners','POST',banner,admin)[0] == 200, 'admin updates banner')
_, html, _ = request('/'); check(b'HTTP &lt;script&gt;fixture&lt;/script&gt;' in html, 'public hero renders escaped database content')
boundary='LaezerTestBoundary'
def multipart(data, filename, mime):
    return (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+data+f'\r\n--{boundary}--\r\n'.encode())
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH1cAAAAASUVORK5CYII=')
s, d, _ = request('/api/portal/admin/upload','POST',multipart(png,'fixture.png','image/png'),admin,extra={'Content-Type':'multipart/form-data; boundary='+boundary})
check(s == 201 and d['url'].startswith('/media/'), 'real multipart upload stores image')
_, content, headers = request(d['url']); check(content == png and headers['Content-Type'] == 'image/png' and headers['X-Content-Type-Options'] == 'nosniff','media returns original bytes and safe MIME')
check(request('/api/portal/admin/upload','POST',multipart(b'<svg onload="alert(1)"></svg>','fake.png','image/png'),admin,extra={'Content-Type':'multipart/form-data; boundary='+boundary})[0] == 400, 'spoofed image upload rejected')
check(request('/laezer-private/config.php')[0] == 404,'private configuration not exposed')
check(request('/api/payments/qpay/callback','POST',{})[0] == 503,'QPay callback remains inactive')
check(request('/api/payments/qpay/callback','POST',{},origin='')[0] == 503,'provider callback without browser origin also remains inactive')
check(request('/api/portal/admin/accounts','POST',{**account,'password':'','active':False},admin)[0] == 200,'admin can deactivate reseller')
check(request('/api/portal/catalog',token=partner)[0] == 401,'deactivation revokes old reseller cookie')
check(request('/api/auth/logout','POST',token=admin)[0] == 200,'logout succeeds')
check(request('/api/portal/admin/products',token=admin)[0] == 401,'logged-out token cannot access admin data')
print(f'OK: {count} local HTTP assertions passed.')
