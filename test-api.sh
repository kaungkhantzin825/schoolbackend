#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────
#  MAVER API diagnostic — run against the live server
#  Usage:  bash test-api.sh [email] [password]
# ──────────────────────────────────────────────────────────────
API="${API:-https://backend.mmcertify.com/api}"
EMAIL="${1:-john@um1.edu}"
PASSWORD="${2:-password123}"

echo "API: $API"
echo "══════════════════════════════════════════════════"

# ── 1. Is the API reachable at all? ──
echo
echo "[1] Public endpoint (no auth needed)"
curl -s -o /dev/null -w "    GET /universities/search  ->  HTTP %{http_code}\n" \
  "$API/universities/search?q=a" -H 'Accept: application/json'

# ── 2. Is .env protected? ──
echo
echo "[2] Security: .env must NOT be reachable"
ENV_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${API%/api}/.env")
if [ "$ENV_CODE" = "200" ]; then
  echo "    !! CRITICAL: /.env is PUBLIC (HTTP 200) — DocumentRoot is wrong"
else
  echo "    OK: /.env -> HTTP $ENV_CODE"
fi

# ── 3. Login ──
echo
echo "[3] Login as $EMAIL"
LOGIN=$(curl -s -X POST "$API/login" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")

TOKEN=$(echo "$LOGIN" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ]; then
  echo "    FAILED. Response:"
  echo "    $LOGIN"
  exit 1
fi
echo "    OK — token: ${TOKEN:0:20}..."

AUTH="Authorization: Bearer $TOKEN"

# ── 4. Does the Authorization header survive Apache? ──
echo
echo "[4] Token check (proves Apache forwards the Authorization header)"
ME=$(curl -s "$API/me" -H "$AUTH" -H 'Accept: application/json' -w '\n%{http_code}')
ME_CODE=$(echo "$ME" | tail -1)
if [ "$ME_CODE" = "200" ]; then
  echo "    OK — GET /me -> 200 (header is reaching PHP)"
else
  echo "    FAILED — GET /me -> $ME_CODE"
  echo "    => Apache is stripping 'Authorization'. Fix public/.htaccess."
  exit 1
fi

# ── 5. Add a student WITHOUT a photo ──
echo
echo "[5] POST /students  (add student, no photo)"
UNI_ID=$(curl -s "$API/me" -H "$AUTH" -H 'Accept: application/json' | sed -n 's/.*"university_id":\([0-9]*\).*/\1/p')
NRC="TEST/$(date +%s)"
ADD=$(curl -s -X POST "$API/students" -H "$AUTH" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"university_id\":$UNI_ID,\"graduate_name\":\"API Test\",\"father_name\":\"U Test\",\"gender\":\"Male\",\"date_of_birth\":\"1999-01-01\",\"nrc_number\":\"$NRC\",\"degree\":\"MBBS\",\"graduation_year\":2023}" \
  -w '\n%{http_code}')
ADD_CODE=$(echo "$ADD" | tail -1)
echo "    HTTP $ADD_CODE"
[ "$ADD_CODE" != "201" ] && echo "    $(echo "$ADD" | head -1)"

# ── 6. Photo upload (the failing one) ──
echo
echo "[6] POST /students/upload-photo  <-- the endpoint returning 500"
printf '\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\x0d\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82' > /tmp/px.png
UP=$(curl -s -X POST "$API/students/upload-photo" -H "$AUTH" \
  -H 'Accept: application/json' -F "photo=@/tmp/px.png" -w '\n%{http_code}')
UP_CODE=$(echo "$UP" | tail -1)
echo "    HTTP $UP_CODE"
echo "    $(echo "$UP" | head -1)"

echo
echo "══════════════════════════════════════════════════"
case "$UP_CODE" in
  200) echo "RESULT: uploads are working." ;;
  401) echo "RESULT: token not accepted -> fix public/.htaccess (Authorization header)." ;;
  403) echo "RESULT: this account lacks permission (needs university_admin or super_admin)." ;;
  413) echo "RESULT: file too large -> raise upload_max_filesize / post_max_size in php.ini." ;;
  500) echo "RESULT: server error. Read the message above, then:  tail -50 storage/logs/laravel.log" ;;
  *)   echo "RESULT: unexpected $UP_CODE" ;;
esac
