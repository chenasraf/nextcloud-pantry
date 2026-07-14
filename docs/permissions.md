# Permissions

Pantry scopes everything to a **house**. Every list, item, photo, and note belongs to a house, and
what a member may do inside that house is decided by the **roles** they hold. This document explains
the model, how to change it, and exactly which capability each action requires.

## Concepts

- **House** — the top-level container. A user must be a member of a house to touch anything in it.
- **Role** — a named bundle of capabilities that lives in a house. There are three kinds:
  - **Admin** (`admin`) — a built-in role that grants **everything**, always. Its capabilities are
    locked on and cannot be edited. Admins also bypass per-list access and sharing restrictions.
  - **Member** (`default`) — the built-in role assigned to ordinary members. It is fully editable.
  - **Custom** (`normal`) — any additional role an admin creates, with whatever capabilities they
    pick.
- **Capability** — a single boolean permission, e.g. `canAddItems`. See the catalog below.
- **Effective permissions** — a member may hold more than one role; their effective capabilities are
  the **union (OR)** across all their roles. Holding any admin role grants all capabilities.

When a house is created, it is seeded with an **Admin** and a **Member** role, and **both start with
every capability granted**. So out of the box every member can do everything; admins tighten this by
editing the Member role or introducing custom roles.

## Capability catalog

| Capability        | Governs                                                                                   |
| ----------------- | ----------------------------------------------------------------------------------------- |
| `canViewLists`    | Viewing checklists and their items (incl. trash & archive views)                          |
| `canCreateLists`  | Creating new checklists                                                                   |
| `canEditLists`    | Editing list settings **and** editing/reordering/archiving items, categories, item images |
| `canDeleteLists`  | Trashing, restoring, and permanently deleting checklists                                  |
| `canAddItems`     | Adding items to a list                                                                    |
| `canCheckItems`   | Toggling an item done/undone                                                              |
| `canCopyItems`    | Copying items to another list                                                             |
| `canMoveItems`    | Moving items to another list                                                              |
| `canDeleteItems`  | Trashing, restoring, and permanently deleting items                                       |
| `canViewPhotos`   | Viewing photos and folders (incl. trash view)                                             |
| `canUploadPhotos` | Uploading photos                                                                          |
| `canUpdatePhotos` | Editing a photo (caption)                                                                 |
| `canMovePhotos`   | Managing folders (create/rename/reorder/delete) and moving/reordering photos              |
| `canDeletePhotos` | Trashing, restoring, and permanently deleting photos                                      |
| `canViewNotes`    | Viewing notes (incl. trash view)                                                          |
| `canCreateNotes`  | Creating notes                                                                            |
| `canUpdateNotes`  | Editing notes (title/content/color/pin) and reordering                                    |
| `canDeleteNotes`  | Trashing, restoring, and permanently deleting notes                                       |

> Note: there is no separate "edit item" or "archive item" capability. Editing an item, reordering,
> setting its category, changing its image, and **archiving/unarchiving** are all governed by
> `canEditLists`. Trashing an item is governed separately by `canDeleteItems`.

## How enforcement works

Every write endpoint declares the capabilities it needs. A request is checked in this order:

1. **Authenticated** — must be a logged-in user.
2. **House member** — must belong to the `{houseId}` in the route.
3. **Admin short-circuit** — if the user holds an admin role, all remaining checks are skipped.
4. **Required capabilities** — the user's effective capabilities must include every capability the
   action requires.
5. **Per-list access** — when the route targets a specific list, list access is additionally
   enforced (see below).

### Per-list access and sharing

A checklist can be **restricted to specific roles**. If a list has no role restriction it is open to
every member; otherwise only members holding one of the allowed roles (or an admin) can reach it.

Independently, an individual entity (checklist, note, photo, or photo folder) can be **shared with
specific users** at **view** or **edit** level. A share can grant access to someone who would
otherwise have none, but it is bounded:

- A **view** share allows read-only actions only.
- An **edit** share allows editing items but not, e.g., deleting the whole list.
- Sharing **never** grants deleting the list/entity itself.

## How to change permissions

All role and membership management is **admin-only**.

- **Edit a role's capabilities** — toggle capabilities on the Member role or a custom role
  (`PATCH /api/houses/{houseId}/roles/{roleId}`). The Admin role is locked to all-granted.
- **Create / delete custom roles** — `POST` / `DELETE /api/houses/{houseId}/roles`.
- **Assign roles to a member** — `PUT /api/houses/{houseId}/members/{memberId}/roles`.
- **Restrict a list to roles** — `PUT /api/houses/{houseId}/lists/{listId}/roles` (empty = open to
  all).
- **Share an entity with a user** — `PUT /api/houses/{houseId}/shares/{entityType}/{entityId}`. Only
  the entity's creator or a house admin may manage its shares.

House-level management (editing house settings such as trash retention, adding/removing members) is
also admin-only; deleting or leaving a house is governed by house ownership.

---

## Action → capability matrices

Each row is an action; each column is a capability. ✅ marks the capability that action requires.
Admins can perform every action regardless of these matrices. "Trash" means soft-delete
(recoverable); "permanent delete" erases the row.

### Checklists

Columns: **View** = `canViewLists`, **Create** = `canCreateLists`, **Edit** = `canEditLists`,
**Delete** = `canDeleteLists`.

| Action                                                                 | View | Create | Edit | Delete |
| ---------------------------------------------------------------------- | :--: | :----: | :--: | :----: |
| View lists / open a list                                               |  ✅  |        |      |        |
| View the lists trash                                                   |  ✅  |        |      |        |
| Create a list                                                          |      |   ✅   |      |        |
| Edit a list (name, description, icon, color, "delete on done" default) |      |        |  ✅  |        |
| Reorder lists                                                          |      |        |  ✅  |        |
| Trash a list                                                           |      |        |      |   ✅   |
| Restore a list from trash                                              |      |        |      |   ✅   |
| Permanently delete a list                                              |      |        |      |   ✅   |
| Empty the lists trash                                                  |      |        |      |   ✅   |

> Restricting a list to specific roles is admin-only.

### Checklist items

Columns: **View** = `canViewLists`, **Add** = `canAddItems`, **Check** = `canCheckItems`, **Edit** =
`canEditLists`, **Copy** = `canCopyItems`, **Move** = `canMoveItems`, **Delete** = `canDeleteItems`.

| Action                                              | View | Add | Check | Edit | Copy | Move | Delete |
| --------------------------------------------------- | :--: | :-: | :---: | :--: | :--: | :--: | :----: |
| View items                                          |  ✅  |     |       |      |      |      |        |
| View the items trash                                |  ✅  |     |       |      |      |      |        |
| View the items archive                              |  ✅  |     |       |      |      |      |        |
| Add an item                                         |      | ✅  |       |      |      |      |        |
| Toggle an item done / undone                        |      |     |  ✅   |      |      |      |        |
| Edit an item                                        |      |     |       |  ✅  |      |      |        |
| Reorder items                                       |      |     |       |  ✅  |      |      |        |
| Set / clear an item's category                      |      |     |       |  ✅  |      |      |        |
| Upload / clear an item image                        |      |     |       |  ✅  |      |      |        |
| Archive an item                                     |      |     |       |  ✅  |      |      |        |
| Unarchive an item                                   |      |     |       |  ✅  |      |      |        |
| Copy items to another list                          |      |     |       |      |  ✅  |      |        |
| Move items to another list                          |      |     |       |      |      |  ✅  |        |
| Trash an item                                       |      |     |       |      |      |      |   ✅   |
| Restore an item from trash                          |      |     |       |      |      |      |   ✅   |
| Permanently delete an item (incl. from the archive) |      |     |       |      |      |      |   ✅   |
| Empty the items trash                               |      |     |       |      |      |      |   ✅   |

### Photos

Columns: **View** = `canViewPhotos`, **Upload** = `canUploadPhotos`, **Update** = `canUpdatePhotos`,
**Move** = `canMovePhotos`, **Delete** = `canDeletePhotos`.

| Action                         | View | Upload | Update | Move | Delete |
| ------------------------------ | :--: | :----: | :----: | :--: | :----: |
| View photos and folders        |  ✅  |        |        |      |        |
| View the photos trash          |  ✅  |        |        |      |        |
| Upload a photo                 |      |   ✅   |        |      |        |
| Edit a photo caption           |      |        |   ✅   |      |        |
| Move a photo to another folder |      |        |        |  ✅  |        |
| Reorder photos                 |      |        |        |  ✅  |        |
| Create a folder                |      |        |        |  ✅  |        |
| Rename a folder                |      |        |        |  ✅  |        |
| Reorder folders                |      |        |        |  ✅  |        |
| Delete a folder                |      |        |        |      |   ✅   |
| Trash a photo                  |      |        |        |      |   ✅   |
| Restore a photo from trash     |      |        |        |      |   ✅   |
| Permanently delete a photo     |      |        |        |      |   ✅   |
| Empty the photos trash         |      |        |        |      |   ✅   |

### Notes

Columns: **View** = `canViewNotes`, **Create** = `canCreateNotes`, **Update** = `canUpdateNotes`,
**Delete** = `canDeleteNotes`.

| Action                              | View | Create | Update | Delete |
| ----------------------------------- | :--: | :----: | :----: | :----: |
| View notes                          |  ✅  |        |        |        |
| View the notes trash                |  ✅  |        |        |        |
| Create a note                       |      |   ✅   |        |        |
| Edit a note (title, content, color) |      |        |   ✅   |        |
| Pin / unpin a note                  |      |        |   ✅   |        |
| Reorder notes                       |      |        |   ✅   |        |
| Trash a note                        |      |        |        |   ✅   |
| Restore a note from trash           |      |        |        |   ✅   |
| Permanently delete a note           |      |        |        |   ✅   |
| Empty the notes trash               |      |        |        |   ✅   |
