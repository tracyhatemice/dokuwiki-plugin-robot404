# DokuWiki robot404 plugin

Web crawlers have no business visiting actions such as login, search or old revisions. DokuWiki answers a
disabled action with a message and status 200, so crawlers index the result and keep coming back. This plugin
answers them with **404 Not Found** instead.

A robot gets an empty 404 response for:

* **Disallowed actions**: the actions selected in the plugin configuration, plus every action DokuWiki itself
  does not offer (`disableactions`, or actions the authentication backend cannot do, such as `register`).
* **Hidden pages**: pages matching DokuWiki's `hidepages` setting (option `hiddenpages`).
* **Pages it may not read**: pages and namespaces the ACL gives no read access to (option `aclpages`).

With `aclpages` enabled, any other visitor who may not read a page also gets a 404. Setting `aclresponse`
picks how: by default they still see DokuWiki's "Permission Denied" page with its login form, just with
status 404, so a private wiki stays easy to log in to; `plain` gives them the same bare 404 as robots
(logging in then works through `?do=login`).

Pages that a robot would be refused also get `noindex,nofollow` (robots meta tag and `X-Robots-Tag` header)
for every visitor, in case a crawler is not recognized.

## Installation

Install from URL:

`https://github.com/tracyhatemice/dokuwiki-plugin-robot404/zipball/main`

## Configuration

All settings are in the Configuration Manager under *Robot404*:

| Setting          | Default | Meaning |
|------------------|---------|---------|
| `useragents`     | `bot\|crawl\|slurp\|spider\|mediapartners\|Google\|…` | Regular expression matched against the `User-Agent` header to recognize robots. Like DokuWiki's own regex settings it is case insensitive and has no delimiters; escape a `/` as `\/`. |
| `disableactions` | `backlink`, `diff`, `index`, `recent`, `revisions`, `search`, `login`, `edit`, … | Actions disallowed for robots. *XML Syndication* refuses `feed.php` to robots; it is off by default because feed readers often identify like robots (Feedly says `FeedFetcher-Google`). |
| `hiddenpages`    | on      | Refuse hidden pages to robots. |
| `aclpages`       | on      | Send 404 for pages and namespaces the visitor may not read. |
| `aclresponse`    | `denied` | What visitors other than robots get for pages they may not read: `denied` (Permission Denied page, status 404) or `plain` (bare 404). |

To test as a robot, add `?isrobot404=1` to a URL.

## Development

Everything runs in Docker; PHP is not needed on the host. The DokuWiki checkout (default `../../dokuwiki`,
override with `DOKUWIKI_PATH`) and the [dev plugin](https://www.dokuwiki.org/plugin:dev) (default
`../dokuwiki-plugin-dev`, override with `DEV_PLUGIN_PATH`) are mounted read-only and copied into the
containers, so nothing is written to them. The checkout needs its test dependencies (`_test/vendor`) installed.

```bash
docker compose run --rm test                  # unit tests; arguments go to phpunit, e.g. --filter isRobot
docker compose run --rm dev check             # code style (dev plugin); also: fix, addTest, ...
docker compose up -d wiki                     # dev wiki on http://127.0.0.1:8404/ (admin/admin)
docker compose run --rm smoke                 # HTTP checks against the dev wiki, including refused robots
docker compose down -v                        # remove containers and the wiki volume
```

Run as `UID=$(id -u) GID=$(id -g) docker compose …` if your user is not 1000:1000. `docker/wiki.sh` sets up
the dev wiki with a hidden page (`hidden:page`) and a namespace only the admin may read (`private:`).
