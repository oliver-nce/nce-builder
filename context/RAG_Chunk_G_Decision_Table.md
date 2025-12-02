# RAG Chunk G — Decision Table

| Situation | Correct API |
|----------|-------------|
| Update profile attributes only | profile-bulk-import-jobs |
| Web consent → allow marketing | bulk subscribe |
| User opts out of marketing | bulk unsubscribe |
| Hard email block required | suppress |
| Recover from manual suppression | unsuppress |

Invariant:
Never use suppression when unsubscribe is sufficient.
