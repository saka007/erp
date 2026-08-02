import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';

interface TextileSelectOption {
    value: string;
    label: string;
    group?: string;
    disabled?: boolean;
    disabledReason?: string;
}

interface TextileSelectFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: Array<string | TextileSelectOption>;
    required?: boolean;
    includeEmpty?: boolean;
    emptyLabel?: string;
    searchable?: boolean;
    helperText?: string;
    disabled?: boolean;
    disabledReason?: string;
}

export function TextileSelectField({
    label,
    value,
    onChange,
    options,
    required = false,
    includeEmpty = false,
    emptyLabel = 'Select',
    searchable,
    helperText,
    disabled = false,
    disabledReason,
}: TextileSelectFieldProps) {
    const normalizedOptions = options.map((option) => {
        if (typeof option === 'string') {
            return { value: option, label: option };
        }

        return option;
    });

    const optionsByGroup = normalizedOptions.reduce<Record<string, TextileSelectOption[]>>((carry, option) => {
        const group = option.group || '__ungrouped__';
        carry[group] = carry[group] || [];
        carry[group].push(option);
        return carry;
    }, {});

    const groupedKeys = Object.keys(optionsByGroup);
    const hasNamedGroups = groupedKeys.some((group) => group !== '__ungrouped__');
    const showAsDisabled = disabled || normalizedOptions.length === 0;
    const supportingText = showAsDisabled && disabledReason ? disabledReason : helperText;

    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            <Select
                value={value || undefined}
                onValueChange={(nextValue) => onChange(nextValue === '__empty__' ? '' : nextValue)}
                required={required}
                disabled={showAsDisabled}
            >
                <SelectTrigger>
                    <SelectValue placeholder={emptyLabel} />
                </SelectTrigger>
                <SelectContent searchable={searchable ?? normalizedOptions.length > 8}>
                    {includeEmpty ? <SelectItem value="__empty__">{emptyLabel}</SelectItem> : null}
                    {hasNamedGroups
                        ? groupedKeys.map((groupKey) => {
                              const groupedOptions = optionsByGroup[groupKey];

                              if (groupKey === '__ungrouped__') {
                                  return groupedOptions.map((option) => (
                                      <SelectItem key={option.value} value={option.value} disabled={option.disabled}>
                                          {option.label}
                                          {option.disabledReason ? ` (${option.disabledReason})` : ''}
                                      </SelectItem>
                                  ));
                              }

                              return (
                                  <SelectGroup key={groupKey}>
                                      <SelectLabel>{groupKey}</SelectLabel>
                                      {groupedOptions.map((option) => (
                                          <SelectItem key={option.value} value={option.value} disabled={option.disabled}>
                                              {option.label}
                                              {option.disabledReason ? ` (${option.disabledReason})` : ''}
                                          </SelectItem>
                                      ))}
                                  </SelectGroup>
                              );
                          })
                        : normalizedOptions.map((option) => (
                              <SelectItem key={option.value} value={option.value} disabled={option.disabled}>
                                  {option.label}
                                  {option.disabledReason ? ` (${option.disabledReason})` : ''}
                              </SelectItem>
                          ))}
                </SelectContent>
            </Select>
            {supportingText ? <p className="text-xs text-muted-foreground">{supportingText}</p> : null}
        </div>
    );
}
