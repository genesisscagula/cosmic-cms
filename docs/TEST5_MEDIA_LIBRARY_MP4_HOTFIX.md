# Test 5 — Media Library MP4 apply hotfix

Root cause: the Video Media Library Apply path wrote the selected MP4 into `universal_background_video_url`. Existing video Sparks render their own `video_url` above that universal layer, so the old video remained visible and Apply appeared to do nothing.

Fix: for a selected video Spark, Media Library Apply now updates the Spark's native `video_url` through the same direct 0-credit path used by the manual video URL control. Existing layout, text, colors, overlay, poster, Luna AI replacement, and Tests 1–4 are untouched.

Retest: Video hero -> Edit manually -> Video Media Library -> choose uploaded MP4 -> Apply. The visible video should change immediately, remain 0 credits, then persist after Save/Publish and refresh.
