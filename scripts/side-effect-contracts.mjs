#!/usr/bin/env node

import { readFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const requiredFields = {
  jobs: ['name', 'queue', 'arguments'],
  mail: ['mailer', 'action', 'to', 'subject'],
  webhooks: ['event', 'method', 'url', 'body'],
  realtime: ['event', 'stream', 'payload'],
};

const isObject = value =>
  value !== null && typeof value === 'object' && !Array.isArray(value);

export const validateSideEffectContract = contract => {
  const errors = [];
  if (contract.schemaVersion !== 1) errors.push('schemaVersion must be 1');
  if (typeof contract.id !== 'string' || !contract.id) {
    errors.push('id must be a non-empty string');
  }
  if (!isObject(contract.effects)) errors.push('effects must be an object');

  for (const [category, fields] of Object.entries(requiredFields)) {
    const effects = contract.effects?.[category];
    if (!Array.isArray(effects) || effects.length === 0) {
      errors.push(`effects.${category} must be a non-empty array`);
      continue;
    }
    effects.forEach((effect, index) => {
      if (!isObject(effect)) {
        errors.push(`effects.${category}[${index}] must be an object`);
        return;
      }
      for (const field of fields) {
        if (!(field in effect)) {
          errors.push(`effects.${category}[${index}].${field} is required`);
        }
      }
    });
  }
  return errors;
};

const canonical = value => {
  if (Array.isArray(value)) {
    return value
      .map(canonical)
      .sort((left, right) => JSON.stringify(left).localeCompare(JSON.stringify(right)));
  }
  if (isObject(value)) {
    return Object.fromEntries(
      Object.entries(value)
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([key, child]) => [key, canonical(child)]),
    );
  }
  return value;
};

export const compareSideEffects = (expected, actual) => {
  const expectedErrors = validateSideEffectContract(expected);
  const actualErrors = validateSideEffectContract(actual);
  return {
    matches:
      expectedErrors.length === 0 &&
      actualErrors.length === 0 &&
      JSON.stringify(canonical(expected.effects)) ===
        JSON.stringify(canonical(actual.effects)),
    expectedErrors,
    actualErrors,
    expected: canonical(expected.effects),
    actual: canonical(actual.effects),
  };
};

const main = () => {
  const files = process.argv.slice(2);
  if (files.length === 0) {
    console.error('Usage: side-effect-contracts.mjs CONTRACT.json [...]');
    process.exitCode = 2;
    return;
  }
  let valid = true;
  for (const file of files) {
    const contract = JSON.parse(readFileSync(path.resolve(file), 'utf8'));
    const errors = validateSideEffectContract(contract);
    if (errors.length > 0) {
      valid = false;
      console.error(`${file}:\n- ${errors.join('\n- ')}`);
    } else {
      console.log(`${file}: valid`);
    }
  }
  if (!valid) process.exitCode = 1;
};

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main();
}
