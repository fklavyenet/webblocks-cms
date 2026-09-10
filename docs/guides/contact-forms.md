---
guide: true
guide_slug: contact-forms
guide_series: I
guide_order: 40
cms_site: cms-webblocksui-com
cms_locale: en
cms_path: /guides/contact-forms
cms_layout: docs
cms_title: Add A Contact Form And Read Messages
card_description: Put a form on a page, then find what visitors sent.
card_thumbnail: 02-inbox.png
---

# Add A Contact Form And Read Messages

**Goal:** Let visitors write to you, and know where their messages land.
**Time:** 4 minutes
**You need:** A page, and someone who should receive the mail

## Steps

1. In the Main slot, select **Add Block**, then choose **Contact Form**.
2. Fill in the copy: **Heading**, **Intro Text**, **Submit Label**, and the **Success Message** shown after sending.
3. Under **Delivery Settings**, leave **Send email notification** on. Use **Recipient Email Override** only when this one form should go somewhere other than the site's usual address.
4. Under **Consent**, turn on **Require a consent checkbox** if you need one, and write the **Consent notice** next to it.
5. Select **Save New Block**.

> **Screenshot** `01-form.png` — The Contact Form block with copy, delivery, and consent settings.
> Alt: Contact form block showing heading, intro, submit label, success message, delivery and consent settings.

6. Messages arrive in **Contact Messages**. The list shows who wrote, about what, and
   the status. The summary above it shows the last 30 days of checked, allowed,
   quarantined, and spam submissions across the sites you may access.

> **Screenshot** `02-inbox.png` — The Contact Messages inbox.
> Alt: Contact messages inbox listing submissions with their status.

7. Open one to read it in full, mark it **read**, **replied**, **spam**, or **archived**,
   and see whether the email notification actually went out. Mark real campaigns
   **spam** so later copies are recognized; restore a false positive to a normal status.

> **Screenshot** `03-message.png` — A single message with its submission and delivery details.
> Alt: Contact message detail showing the visitor message, submission details, and email notification state.

## Example

```text
Heading:         Tell us about your project
Intro Text:      We read everything and answer within two working days.
Submit Label:    Send message
Success Message: Thanks — we have your message and will be in touch.
```

## Where The Mail Goes

The recipient is resolved in order: the block's **Recipient Email Override**, then the site's contact recipient, then the installation's configured fallback.

Set it on the **site**, not on every block. Overrides are for the one form that genuinely belongs to someone else — a jobs page going to a different inbox.

## Notes

- **The message is stored whether or not the email arrives.** The detail screen separates the two, so a delivery problem never means lost enquiries.
- Forms carry signed timing and honeypot checks. A submission made faster than the hard
  minimum is silently discarded, which is why an automated test that fills and submits
  instantly sees nothing arrive.
- Valid submissions are also scored locally. Suspicious messages are retained as
  **Quarantined** or **Spam**, but their email notification is suppressed. The filter
  shares repetition, sender, exact-IP, network-prefix, and learned reputation signals
  with Comments and WebBlocks Forms on the same site.
- **Archive** does not train protection. Use **Spam** for actual abuse and restore false
  positives to New, Read, or Replied.
- Write the **Success Message** as a promise you keep. "We will be in touch" sets an expectation; "Message received" sets none.
- The consent notice is your text, not boilerplate the CMS writes for you. If you collect it, say what you are collecting and why.

**Next:** [Comments And Ratings](/guides/comments-and-ratings)
