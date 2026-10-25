import * as React from "react"
import { Slider as SliderPrimitive } from "radix-ui"

import { cn } from "@/lib/utils"

// A single-thumb shadcn slider in the brand green, matching Switch. The thumb is 20px so it is
// easy to grab with a mouse; arrow keys, Page Up/Down and Home/End move it from the keyboard.
// The thumb is what screen readers focus, so the label and value text are passed to it.
function Slider({
  className,
  "aria-label": ariaLabel,
  "aria-labelledby": ariaLabelledBy,
  "aria-valuetext": ariaValueText,
  ...props
}: React.ComponentProps<typeof SliderPrimitive.Root> & {
  "aria-valuetext"?: string
}) {
  return (
    <SliderPrimitive.Root
      data-slot="slider"
      className={cn(
        "relative flex w-full touch-none items-center py-2 select-none data-[disabled]:opacity-50",
        className
      )}
      {...props}
    >
      <SliderPrimitive.Track
        data-slot="slider-track"
        className="relative h-1.5 w-full grow overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
      >
        <SliderPrimitive.Range
          data-slot="slider-range"
          className="absolute h-full bg-brand-green"
        />
      </SliderPrimitive.Track>
      <SliderPrimitive.Thumb
        data-slot="slider-thumb"
        aria-label={ariaLabel}
        aria-labelledby={ariaLabelledBy}
        aria-valuetext={ariaValueText}
        className="block size-5 cursor-grab rounded-full border-2 border-brand-green bg-white shadow-sm transition-shadow outline-none hover:ring-4 hover:ring-brand-green/20 focus-visible:ring-4 focus-visible:ring-brand-green/40 active:cursor-grabbing disabled:pointer-events-none"
      />
    </SliderPrimitive.Root>
  )
}

export { Slider }
