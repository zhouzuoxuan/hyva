# Lencarta_Ui

Reusable frontend UI building blocks for Hyvä-first Magento projects.

## What it gives you

- `Lencarta\Ui\ViewModel\Component` for shared Tailwind class maps
- Reusable UI templates:
  - `Lencarta_Ui::components/alert.phtml`
  - `Lencarta_Ui::components/badge.phtml`

## Example usage

```xml
<block class="Magento\Framework\View\Element\Template"
       name="lencarta.example.alert"
       template="Lencarta_Ui::components/alert.phtml">
    <arguments>
        <argument name="title" xsi:type="string">Shipping update</argument>
        <argument name="message" xsi:type="string">Orders placed before 2pm are dispatched the same day.</argument>
        <argument name="variant" xsi:type="string">info</argument>
    </arguments>
</block>
```
