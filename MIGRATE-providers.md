Guide to providers migration
============================

```
app/console doctrine:schema:update --force
app/console stage1:project:fix-provider
app/console stage1:user:fix
app/console stage1:project:github:fix
app/console stage1:pull-request:fix
app/console cache:clear
```