#!/usr/bin/env python3
"""Fake Gmail: SMTP with STARTTLS on 4465. Each command costs LAT seconds (round trip + server work), like a real provider."""
import socket, ssl, threading, time, sys, base64
LAT = float(sys.argv[1]) if len(sys.argv) > 1 else 0.12
ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER); ctx.load_cert_chain('cert.pem', 'key.pem')   # openssl req -x509 -newkey rsa:2048 -nodes -keyout key.pem -out cert.pem -days 2 -subj /CN=localhost
count = 0; lock = threading.Lock(); active = 0; peak = 0
def handle(raw):
    global count, active, peak
    with lock:
        active += 1; peak = max(peak, active)
    try:
        time.sleep(LAT)                          # TCP connect
        conn = raw
        f = conn.makefile('rwb', buffering=0)
        def say(s): f.write((s + '\r\n').encode())
        time.sleep(LAT); say('220 sink ESMTP')
        data = False; auth = 0
        while True:
            line = f.readline()
            if not line: break
            t = line.decode(errors='ignore').strip()
            if data:
                if t == '.':
                    data = False; time.sleep(LAT); say('250 queued')
                    with lock: count += 1
                continue
            time.sleep(LAT)
            u = t.upper()
            if auth == 1: auth = 2; say('334 UGFzc3dvcmQ6'); continue
            if auth == 2: auth = 0; say('235 ok'); continue
            if u.startswith('EHLO'): say('250-sink\r\n250-STARTTLS\r\n250 AUTH LOGIN')
            elif u == 'STARTTLS':
                say('220 ready'); time.sleep(LAT * 2)    # TLS handshake
                conn = ctx.wrap_socket(raw, server_side=True); f = conn.makefile('rwb', buffering=0)
            elif u.startswith('AUTH LOGIN'): auth = 1; say('334 VXNlcm5hbWU6')
            elif u.startswith('MAIL'): say('250 ok')
            elif u.startswith('RCPT'): say('250 ok')
            elif u == 'DATA': say('354 go'); data = True
            elif u.startswith('QUIT'): say('221 bye'); break
            else: say('250 ok')
    except Exception as e:
        pass
    finally:
        with lock: active -= 1
        try: raw.close()
        except Exception: pass
def stats():
    while True:
        time.sleep(5)
        with lock: print(f'sink: delivered={count} active={active} peak_active={peak}', flush=True)
threading.Thread(target=stats, daemon=True).start()
s = socket.socket(); s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1); s.bind(('127.0.0.1', 4465)); s.listen(512)
while True:
    raw, _ = s.accept(); threading.Thread(target=handle, args=(raw,), daemon=True).start()
