#!/usr/bin/env bash
# map_all_d7_templates.sh
# Map ALL D7 *.tpl.php templates to D11 *.html.twig stubs in driven11_responsive.

set -euo pipefail

# ---- CONFIG ----
D7="/home/drupal11.drivenracing.com/d7_themes/driven"
DRUPAL_ROOT="web"
THEME="driven11_responsive"
DEST_BASE="$DRUPAL_ROOT/themes/custom/$THEME/templates"

# ---- PREP ----
if [ ! -d "$D7" ]; then
  echo "ERROR: D7 path not found: $D7" >&2
  exit 1
fi

mkdir -p "$DEST_BASE"/{layout,block,views,content,field,misc}

echo "== Mapping ALL D7 *.tpl.php -> D11 *.html.twig"
echo "   D7:  $D7"
echo "   D11: $DEST_BASE"
echo

# ---- HELPERS ----
twig_name () {  # page.tpl.php -> page.html.twig ; page--front.tpl.php -> page--front.html.twig
  local base="$(basename "$1")"
  printf '%s\n' "${base%.tpl.php}.html.twig"
}

dest_dir_for () {
  local name="$1"
  case "$name" in
    html*.tpl.php|maintenance-page*.tpl.php) echo "$DEST_BASE/layout" ;;
    page*.tpl.php|region*.tpl.php)           echo "$DEST_BASE/layout" ;;
    block*.tpl.php)                           echo "$DEST_BASE/block" ;;
    node*.tpl.php|comment*.tpl.php)           echo "$DEST_BASE/content" ;;
    field*.tpl.php)                           echo "$DEST_BASE/field" ;;
    views-*.tpl.php|views*.tpl.php)           echo "$DEST_BASE/views" ;;
    search-result*.tpl.php)                   echo "$DEST_BASE/content" ;;
    *)                                        echo "$DEST_BASE/misc" ;;
  esac
}

write_skeleton () {
  local kind="$1" out="$2"

  case "$kind" in
    html*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 html*.tpl.php. Adjust as needed. #}
<!DOCTYPE html>
<html{{ html_attributes }}>
  <head>
    <head-placeholder token="{{ placeholder_token|raw }}">
    <css-placeholder token="{{ placeholder_token|raw }}">
    <js-placeholder token="{{ placeholder_token|raw }}">
  </head>
  <body{{ attributes.addClass('stability-legacy') }}>
    {{ page_top }}
    {{ page }}
    {{ page_bottom }}
    <js-bottom-placeholder token="{{ placeholder_token|raw }}">
  </body>
</html>
TWIG
    ;;
    maintenance-page*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 maintenance-page.tpl.php. #}
<!DOCTYPE html>
<html{{ html_attributes }}>
  <head>
    <head-placeholder token="{{ placeholder_token|raw }}">
    <css-placeholder token="{{ placeholder_token|raw }}">
    <js-placeholder token="{{ placeholder_token|raw }}">
  </head>
  <body{{ attributes }}>
    <main role="main">
      {{ page.content }}
    </main>
    <js-bottom-placeholder token="{{ placeholder_token|raw }}">
  </body>
</html>
TWIG
    ;;
    page*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 page*.tpl.php. #}
<header class="header-wrapper">
  <div class="header-top">{{ page.header_top }}</div>
  <div class="header main-header">
    <div class="branding">
      {% if logo %}<a href="{{ path('<front>') }}" class="site-logo" rel="home"><img src="{{ logo }}" alt="{{ site_name }}" /></a>{% endif %}
      {% if site_name %}<span class="site-name">{{ site_name }}</span>{% endif %}
    </div>
    <nav class="primary-nav">{{ page.primary_menu }}</nav>
  </div>
</header>

{% if page.highlighted %}
  <section class="highlighted">{{ page.highlighted }}</section>
{% endif %}

<div class="main-container {{ page.sidebar_first ? 'has-sidebar-first' : '' }} {{ page.sidebar_second ? 'has-sidebar-second' : '' }}">
  <main role="main" class="content-wrapper">
    {{ page.breadcrumb }}
    {{ page.content }}
  </main>
  {% if page.sidebar_first %}<aside class="sidebar-first">{{ page.sidebar_first }}</aside>{% endif %}
  {% if page.sidebar_second %}<aside class="sidebar-second">{{ page.sidebar_second }}</aside>{% endif %}
</div>

<footer class="footer-wrapper">
  <div class="footer-top">{{ page.footer_top }}</div>
  <div class="footer">{{ page.footer }}</div>
  <div class="footer-bottom">{{ page.footer_bottom }}</div>
</footer>
TWIG
    ;;
    region*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 region*.tpl.php. #}
<div class="region region-{{ region|clean_class }}">
  {{ content }}
</div>
TWIG
    ;;
    block*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 block*.tpl.php. #}
<div{{ attributes.addClass('block', 'block--' ~ configuration.provider|clean_class, 'block--' ~ plugin_id|clean_class) }}>
  {% if label %}
    <h2{{ title_attributes.addClass('block-title') }}>{{ label }}</h2>
  {% endif %}
  <div class="block__content">{{ content }}</div>
</div>
TWIG
    ;;
    node*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 node*.tpl.php. #}
<article{{ attributes.addClass('node', 'node--type-' ~ node.bundle|clean_class) }}>
  {% if label and not page %}<h2{{ title_attributes }}><a href="{{ url }}">{{ label }}</a></h2>{% endif %}
  <div{{ content_attributes.addClass('node__content') }}>
    {{ content }}
  </div>
</article>
TWIG
    ;;
    field*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 field*.tpl.php. #}
{# Variables: items, label_hidden, label, attributes, title_attributes #}
<div{{ attributes.addClass('field', 'field--name-' ~ field_name|clean_class) }}>
  {% if not label_hidden %}<div{{ title_attributes.addClass('field__label') }}>{{ label }}</div>{% endif %}
  <div class="field__items">
    {% for item in items %}
      <div{{ item.attributes.addClass('field__item') }}>
        {{ item.content }}
      </div>
    {% endfor %}
  </div>
</div>
TWIG
    ;;
    comment*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 comment*.tpl.php. #}
<article{{ attributes.addClass('comment') }}>
  {% if author %}<div class="comment__author">{{ author }}</div>{% endif %}
  <div class="comment__content">{{ content }}</div>
</article>
TWIG
    ;;
    views-view*|views*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 views*.tpl.php. #}
{# Common variants: views-view, views-view-unformatted, views-view-fields, views-view-grid, views-view-table #}
<div class="view {{ id }} view-display-id-{{ display_id }}">
  {% if header %}<div class="view-header">{{ header }}</div>{% endif %}
  {% if exposed %}<div class="view-filters">{{ exposed }}</div>{% endif %}
  {% if attachment_before %}<div class="attachment attachment-before">{{ attachment_before }}</div>{% endif %}
  {% if rows %}
    <div class="view-content">{{ rows }}</div>
  {% elseif empty %}
    <div class="view-empty">{{ empty }}</div>
  {% endif %}
  {% if pager %}{{ pager }}{% endif %}
  {% if attachment_after %}<div class="attachment attachment-after">{{ attachment_after }}</div>{% endif %}
  {% if more %}{{ more }}{% endif %}
  {% if footer %}<div class="view-footer">{{ footer }}</div>{% endif %}
</div>
TWIG
    ;;
    search-result*)
      cat > "$out" <<'TWIG'
{# Auto-generated from D7 search-result.tpl.php. #}
<article class="search-result">
  {% if title %}<h3 class="search-result__title"><a href="{{ url }}">{{ title|raw }}</a></h3>{% endif %}
  {% if snippet %}<div class="search-result__snippet">{{ snippet }}</div>{% endif %}
  {% if info %}<div class="search-result__info">{{ info }}</div>{% endif %}
</article>
TWIG
    ;;
    *)
      # Fallback: write a harmless stub with a note.
      cat > "$out" <<'TWIG'
{# Auto-generated from a D7 tpl.php with no direct D11 equivalent context.
   This file is intentionally minimal; fill in as needed and ensure variables exist. #}
<div class="legacy-template-stub">
  {{ content|default('') }}
</div>
TWIG
    ;;
  esac
}

created=()
skipped=()

# ---- PROCESS ALL *.tpl.php ----
while IFS= read -r -d '' SRC; do
  base="$(basename "$SRC")"             # e.g., page--front.tpl.php
  twig="$(twig_name "$SRC")"            # e.g., page--front.html.twig
  dest_dir="$(dest_dir_for "$base")"
  out="$dest_dir/$twig"

  mkdir -p "$dest_dir"

  if [ -e "$out" ]; then
    skipped+=("$out")
    continue
  fi

  kind="$base"                           # for case switching in write_skeleton
  write_skeleton "$kind" "$out"
  # Annotate with source path
  sed -i "1s|^|{# Source: $SRC #}\n|" "$out"
  created+=("$out")
done < <(find "$D7" -type f -name '*.tpl.php' -print0)

# ---- REPORT ----
echo
echo "== Created (${#created[@]}):"
printf ' - %s\n' "${created[@]}" || true
echo
echo "== Skipped (already existed) (${#skipped[@]}):"
printf ' - %s\n' "${skipped[@]}" || true

# ---- PERMS & CACHE ----
chmod -R a+r "$DEST_BASE"
find "$DEST_BASE" -type d -exec chmod a+rx {} \; >/dev/null 2>&1 || true

echo
echo "Rebuilding caches..."
drush --root="$DRUPAL_ROOT" cr
echo "✓ Done."
