---
guide: true
guide_slug: video-and-audio
guide_series: D
guide_order: 20
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/video-and-audio
cms_layout: docs
cms_title: Add A Video Or Audio Block
card_description: Embed a hosted clip or point at one that lives somewhere else.
card_thumbnail: 01-video-form.png
---

# Add A Video Or Audio Block

**Goal:** Put a video or an audio clip on a page.
**Time:** 3 minutes
**You need:** A page, and either an uploaded file or an address to play from

## Steps

1. In the Main slot, select **Add Block**, then choose **Video**.
2. Fill in **Video Title** and **Supporting Copy**.
3. Then pick one source: either **Choose from Media** for a file you uploaded, or paste an address into **External Video URL**.

> **Screenshot** `01-video-form.png` — The Video form with a title, an external URL, and supporting copy.
> Alt: Video block form showing video title, external video URL, and supporting copy.

4. Select **Save New Block**.
5. For audio, repeat with the **Audio** block: **Audio Title**, **Supporting Copy**, and either a hosted file or **External Audio URL**.

> **Screenshot** `02-audio-form.png` — The Audio form filled in with an external address.
> Alt: Audio block form showing audio title, external audio URL, and supporting copy.

> **Screenshot** `03-rendered.png` — The video block rendered on the public page.
> Alt: Public page showing the video block with its title and supporting copy.

## Example

```text
Video
  Video Title:         Studio reel 2026
  External Video URL:  https://www.youtube.com/watch?v=…
  Supporting Copy:     Ninety seconds of the work we shipped this year.

Audio
  Audio Title:         Episode 4 — designing in the open
  External Audio URL:  https://cdn.example.com/atlas/episode-04.mp3
  Supporting Copy:     Our monthly conversation about work in progress.
```

## Hosted Or External?

**Hosted** means the file lives in your Media Library. You control it, it will not disappear, and nothing third-party runs on your page. It also means you pay for the bandwidth, and large video files are large.

**External** means a provider serves it. Cheaper and better at streaming, but the provider decides what else loads with it, and the clip can be taken down without telling you.

For a short clip you own, host it. For a long video with an audience, a provider usually wins.

## Notes

- **Supporting copy is not optional in practice.** Some visitors cannot play your clip — no sound, poor connection, no autoplay. A sentence of context is the difference between a dead rectangle and usable content.
- If a video carries information that exists nowhere else on the page, it needs captions or a transcript. A block cannot fix that for you.
- Fill in one source, not both. A hosted file and an external URL on the same block is ambiguous.
- Audio files are stored as ordinary media, so the same library, folders, and reuse rules apply.

**Next:** [Offer A Downloadable File](/guides/downloads)
