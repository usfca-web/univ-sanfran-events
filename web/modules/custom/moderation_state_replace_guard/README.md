# Moderation State Replace Guard

This custom Drupal module forces **Replace the current value** (and hides/disables **Append to current value**) for the moderation state "change method" radios used by bulk-edit forms.

It targets the specific element name/path shown in your markup:

`node[event][moderation_state_change_method]`

## Install

1. Copy this module folder into:
   `web/modules/custom/moderation_state_replace_guard`
2. Enable it:
   - Drush: `drush en moderation_state_replace_guard -y`
   - Or via **Extend** in the admin UI.

## Notes / Adjustments

- This is currently keyed to the element path:
  `$form['node']['event']['moderation_state_change_method']`

If your bulk edit form uses a different entity bundle key than `event` (e.g. `article`, `page`, etc.),
you'll need to adjust the array path accordingly.
