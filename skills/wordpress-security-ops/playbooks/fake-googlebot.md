# Fake Googlebot and Scraping

Without BotBlocker, use the access log, FCrDNS and the host firewall as described in `../references/without-botblocker.md`.

## Symptoms

- Requests claim to be Googlebot but come from hosting or VPN ranges.
- Content is scraped and republished.
- Traffic is high but conversions are low.
- One ASN or subnet dominates the logs.

## Verify a Claimed Googlebot

A user agent string proves nothing. Use forward-confirmed reverse DNS (FCrDNS) and the official range:

```bash
# 1. Reverse DNS for the client IP
dig +short -x 66.249.66.1

# 2. Forward DNS for the returned name; it must resolve back to the same IP
dig +short crawl-66-249-66-1.googlebot.com

# 3. Compare against the published Googlebot ranges
curl -sS https://developers.google.com/static/search/apis/ipranges/googlebot.json
```

If the reverse name is not under `googlebot.com` or `google.com`, or the forward lookup does not return the original IP, treat the client as fake. Apply the same method to Bingbot and Yandex before allowing them.

## First Checks

- [ ] `get-top-ips` and `get-top-entities` by ASN and UA.
- [ ] `get-event-details` for UA, path and referer.
- [ ] Verify Googlebot with reverse DNS and forward DNS.
- [ ] Check the official Google IP ranges.
- [ ] Check rate limits and bot rules.
- [ ] Confirm the scraping is not a legitimate partner or CDN.

## Containment

- Block the fake source IP or ASN.
- Keep real Googlebot and other verified crawlers allowed.
- Add a user-agent rule only if it does not break real users.
- Tighten rate limits for suspicious paths.
- Do not block all bots.

## Verification

- [ ] Real Googlebot still crawls.
- [ ] Fake crawler is blocked.
- [ ] Search Console fetch works.
- [ ] No drop in legitimate traffic.
- [ ] Rate limits are documented.

## Stop Conditions

- The source is a CDN or hosting provider shared with legitimate users.
- Blocking would affect search indexing.
- The client has no authorization to change firewall rules.
