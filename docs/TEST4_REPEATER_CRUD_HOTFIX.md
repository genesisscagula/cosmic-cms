# Test 4 Repeater CRUD Hotfix

Bento Services Premium:
- Fresh builds still display 5 services by default.
- The schema now carries service slots 6 and 7 plus service_count.
- Luna can add up to two more services without replacing/redesigning the Spark.
- Luna can remove the least important service by compacting sequential fields and reducing service_count.
- Builder and static export both honor service_count.
- Older saved blocks are normalized for Luna at request time; they are not mutated until the user requests a CRUD change.
- The edit guard now permits the new counted-repeater fields while retaining the existing schema guard for other Sparks.
