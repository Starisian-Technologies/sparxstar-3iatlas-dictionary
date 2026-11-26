# Dictionary Node display adapter — deployment reference

**Governing record:** `docs/adr/DICT-ADR-001-display-json-and-wordpress-display-adapter.md`
**Contract:** `.github/instructions/3IATLAS-DICTIONARY-DISPLAY-JSON-CONTRACT-v1.0.md`

This file documents how an operator configures the adapter. It states **no
values**: every identifier below is deployment-specific, is issued when this
site is registered as its own identity service client and its own Dictionary
caller row, and is never committed to this repository.

---

## What the adapter is

```
reader's browser
  → same-origin  /wp-json/sparxstar/v1/dictionary/display/*   (this plugin)
  → machine token POST {identity}/oauth2/token                 (private_key_jwt, audience=dictionary)
  → private       GET  {node}/v1/display/*                     (scope: display)
```

WordPress renders; the Dictionary Node owns the words. No dictionary record is
copied, synced, mirrored or cached into any WordPress store — no CPT row, no
post meta, no taxonomy term, no option, no transient. The **only** upstream
value this plugin persists is the machine access token.

The browser never learns the Node's URL, never holds a dictionary credential and
never receives a signed upstream header.

---

## Configuration

Each setting is read from a PHP constant in `wp-config.php` and may then be
overridden by a filter, so a deployment can source values from a secret manager
without editing plugin code. **None has a default.** An unconfigured deployment
reports its missing constants in an admin notice and refuses to call upstream.

| Constant | Filter | Required | What it is |
| :-- | :-- | :-- | :-- |
| `SPARXSTAR_DICT_IDENTITY_TOKEN_ENDPOINT` | `sparxstar_dictionary_identity_token_endpoint` | yes | The identity node's token endpoint URL. Also the assertion's `aud`, compared for equality upstream. |
| `SPARXSTAR_DICT_NODE_BASE_URL` | `sparxstar_dictionary_node_base_url` | yes | The Dictionary Node's base URL. |
| `SPARXSTAR_DICT_CLIENT_ID` | `sparxstar_dictionary_client_id` | yes | This site's registered identity `client_id`. Its own — never the WordPad or Games credential. |
| `SPARXSTAR_DICT_CLIENT_KID` | `sparxstar_dictionary_client_kid` | yes | The registered `kid` of the signing key. |
| `SPARXSTAR_DICT_PRIVATE_KEY_PATH` | `sparxstar_dictionary_private_key_path` | yes | Absolute path of the RSA private key. See below. |
| `SPARXSTAR_DICT_DISPLAY_ADAPTER` | `sparxstar_dictionary_display_adapter_enabled` | no | The cutover flag. **Defaults to off.** |
| `SPARXSTAR_DICT_READER_REF_SALT` | `sparxstar_dictionary_reader_ref_salt` | no | Salt for the opaque reader reference. Falls back to `wp_salt( 'auth' )`. |
| `SPARXSTAR_DICT_NODE_TIMEOUT` | `sparxstar_dictionary_node_timeout` | no | Upstream timeout in seconds. |
| `SPARXSTAR_DICT_NODE_MAX_BYTES` | `sparxstar_dictionary_node_max_bytes` | no | Ceiling on an upstream response body, in bytes. |

Both URLs must be `https`. Plain HTTP is refused except to a loopback host — a
bearer credential travels on these requests.

### The private key

- It lives **outside the plugin directory and outside the web root**. The
  adapter validates this and refuses to operate, with an admin error naming the
  constant, if the key is inside either.
- It is never in the repository, never in an option row, never in a build
  artifact, and never in a response, a log or an error message.
- It is read directly by OpenSSL (`file://`) and its contents are never held in
  a PHP string, echoed, or logged.
- Recommended ownership: readable only by the web server user (`0400`/`0600`).

---

## The one product setting

**Dictionary → Display** in the admin carries exactly one setting: which
languages this deployment shows.

| Mode | Behaviour | Public language selector |
| :-- | :-- | :-- |
| `single` | One ISO 639-3 code | Hidden |
| `selected` | An allowlist of ISO 639-3 codes | Shows only those |
| `all_available` | Every language the Node reports | Shows all reported |

Codes are stored; **names are never stored and never mapped locally**. Every
readable language and domain name comes from the Node, including a Node that
reports a code as its own name. A code the Node does not report is rejected when
you save. A language that later stops being reported degrades to a notice — never
to a fatal, a blank page, or a silent switch to a different language.

`all_available` expands the *choices*. It never fetches entries for more than the
one language in play, and never issues a request per language.

---

## Cutover order

1. The contract and `DICT-ADR-001` land in both repos. *(done)*
2. **The Node ships and deploys its `/v1/display/*` JSON routes.**
3. This site is registered: its own identity service client with
   `allowed_audiences: ['dictionary']`, and its own Dictionary caller row with
   `scope=display` and an entry budget.
4. Set the constants, choose the language mode, then switch
   `SPARXSTAR_DICT_DISPLAY_ADAPTER` on and verify against the live Node.
5. Only then is the legacy WordPress dictionary API retired as a data authority,
   and the CPT/SCF read path and its WPGraphQL lexical queries are deleted.

**Step 2 must be deployed before step 4 is enabled.** A plugin that switches
first is a dictionary serving blank pages.

---

## What changes for a reader when the flag is on

Browse becomes search-first. There is no all-entries route on this seam and no
pagination — that is the anti-enumeration invariant, not a gap — so:

- the A–Z jump bar and the full alphabetical list are not shown;
- the search box does not advertise a corpus count, because exact counts are
  suppressed upstream;
- the audio/image/part-of-speech filter pills are not shown, because a search
  result carries a headword, not an entry;
- favourites and history hold slugs only, so a saved word shows its slug until
  it is opened.

---

## Failure behaviour

Every upstream failure — timeout, unreachable Node, `401`, `403`,
`429 budget_exceeded`, `404`, malformed JSON, an HTML body, an oversized body —
produces a controlled WordPress error with a reader-safe message. A blank page,
a fatal, or a PHP notice carrying upstream detail is a defect.

An HTML body is never parsed and never rendered. The Node's `/w/:slug` and
`/search` return HTML by design, so a mistyped base URL or a captive proxy
yields a `200` whose body is a web page; the adapter refuses it on content type
before any parsing happens.
