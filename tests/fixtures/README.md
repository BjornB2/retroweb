# Extraction fixtures

These small, network-free fixtures model structures seen on news overviews,
articles, Wikipedia-like documents, blogs, search results, live feeds and
malformed pages. They contain no copied article corpus or personal data.

`tests/run.php` verifies classification and meaningful-content retention.
Inspect an individual decision with:

```sh
php bin/analyse.php tests/fixtures/nos-overview.html
```

The diagnostic command reads local files only and reports counts, selected
content size and removed navigation regions. It is not a public web endpoint.
