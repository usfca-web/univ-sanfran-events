# Feed Title Lock

This module disables the **Title** field on edit forms for the **Event** content type, unless the user has the **administrator** role.

## Purpose

The Event content is imported from an external feed. To prevent users from accidentally changing feed-managed data, this module locks the **Title** field so that it remains visible but cannot be edited by non-admin users.

Administrators, however, retain the ability to edit the title when necessary.

---

## Features

- Automatically detects the `event` content type edit form (`node_event_edit_form`).
- Disables the title field for all users except administrators.
- Leaves the field visible and readable for clarity.
- Displays a helper note: “This title is managed automatically from an external feed.”

---

## Installation

1. Place the module in your Drupal installation under: /web/modules/custom