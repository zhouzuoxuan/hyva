# Hyvä UI - banner.A - default

[![License]](../../../LICENSE.md)
[![Hyva Supported Versions]](https://docs.hyva.io/hyva-ui-library/getting-started.html)
[![Tailwind Supported Versions]](https://tailwindcss.com/)
[![Figma]](https://www.figma.com/@hyva)
[![included-with-hyva-cms]](https://www.hyva.io/hyva-commerce.html)

The Banner component provides a simple and effective way to display prominent messages to your users.
It's ideal for promotions, important information, or guiding user attention.
It is fully responsive and can be easily customized with your own content and styling.

## Usage - Template

1. Copy or merge the following files/folders into your theme:
   * `Magento_Cms/templates/elements/banner-a.phtml`
2. Adjust the content and code to fit your own needs and save
3. Create your development or production bundle by running `npm run watch` or `npm run build` in your
   theme's tailwind directory

> There's an example how to insert the `banner-a.phtml` into your homepage in `Magento_Cms/layout/cms_index_index.xml`

### Configuration Options

This UI component offers customization options without modifying the corresponding phtml files.

To configure this UI component,
utilize the provided options as outlined in the `src/Magento_Theme/layout/cms_index_index.xml` file.

| Option Name         | Type    | Available Values    | Default     | Description                                                              |
| ------------------- | ------- | ------------------- | ----------- | ------------------------------------------------------------------------ |
| `title`             | string  |                     |             | The main title of the banner.                                            |
| `subtitle`          | string  |                     |             | The subtitle of the banner.                                              |
| `content_alignment` | string  |                     | end stretch | Controls the alignment of the entire content block, using css values.    |
| `text_align`        | string  | start, center, end  | center      | Controls the alignment of the text.                                      |
| `background_color`  | string  | any CSS color value | #1e293b     |                                                                          |
| `text_color`        | string  | any CSS color value | #f8fafc     |                                                                          |
| `image`             | array   |                     |             | Image for the banner background. Expects an array with src and alt.      |
| `desktop_image`     | string  |                     |             | A separate image source for desktop viewports.                           |
| `loading`           | string  | lazy, eager         | lazy        | Sets the loading strategy for the image.                                 |
| `link`              | array   |                     |             | Adds a link to the banner. Expects url, label, and optional open_in_new. |
| `link_appearance`   | string  | _(Link Options)_    | btn-primary | Style of the link.                                                       |
| `use_card`          | boolean | true, false         | false       | Wraps the text content in a card-like container for better readability.  |
| `use_gradient`      | boolean | true, false         | false       | Adds a gradient overlay on top of the image, instead of cover.           |
| `gradient_angle`    | number  | 0-360               | 5           | Sets the angle of the gradient.                                          |

> Link Options: `btn-primary`, `btn-secondary`, `btn`, `link`, `overlay`.

## Preview

![Preview of Banner A](./media/A-default.jpg)

## License

Hyvä Themes - https://hyva.io

Copyright © Hyvä Themes B.V 2020-present. All rights reserved.

This product is licensed per Magento install. Please see the LICENSE.md file in the root of this repository for more
information.

[License]: https://img.shields.io/badge/License-004d32?style=for-the-badge "Link to Hyvä License"
[Figma]: https://img.shields.io/badge/Figma-gray?style=for-the-badge&logo=Figma "Link to Figma"
[CMS Tailwind JIT]: https://docs.hyva.io/hyva-themes/cms/using-tailwind-classes-in-cms-content.html
[included-with-hyva-cms]: https://img.shields.io/badge/Hyv%C3%A4_CMS-109c85?style=for-the-badge

[Hyva Supported Versions]: https://img.shields.io/badge/Hyv%C3%A4-1.3,_1.4-0A23B9?style=for-the-badge&labelColor=0A144B "Hyvä Supported Versions"
[Tailwind Supported Versions]: https://img.shields.io/badge/Tailwind-3,_4-06B6D4?style=for-the-badge&logo=TailwindCSS "Tailwind Supported Versions"
