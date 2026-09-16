import { describe, expect, it } from 'vitest'

import { canStepDown, stepQuantity } from './quantity'

const up = (q: string) => stepQuantity(q, 1)
const down = (q: string) => stepQuantity(q, -1)

/** Every value one tap apart, from the floor up. */
const GRID = '1 2 3 4 5 6 7 8 9 10 15 20 25 30 35 40 45 50 60 70 80 90 100 150 200 250'.split(' ')

/**
 * What one tap on the quantity stepper means.
 *
 * The step scales with the value, so "400 g" reaches "500 g" in two taps
 * instead of a hundred — while small counts still move one at a time.
 */
describe('stepQuantity', () => {
  it('counts one at a time below ten', () => {
    expect(up('0')).toBe('1')
    expect(up('3')).toBe('4')
    expect(up('9')).toBe('10')
    expect(down('4')).toBe('3')
    expect(down('2')).toBe('1')
  })

  it('never goes below one', () => {
    expect(down('1')).toBe('1')
    expect(down('0')).toBe('0')
  })

  it('widens the step as the value grows', () => {
    const values = ['0']
    for (let i = 0; i < GRID.length; i++) {
      values.push(up(values[values.length - 1]))
    }
    expect(values).toEqual(['0', ...GRID])
  })

  it('walks back down the same grid it walked up', () => {
    const values = ['250']
    for (let i = 0; i < GRID.length - 1; i++) {
      values.push(down(values[values.length - 1]))
    }
    expect([...values].reverse()).toEqual(GRID)
  })

  it('pulls a value between grid points onto the grid', () => {
    expect(up('123')).toBe('150')
    expect(down('123')).toBe('100')
    expect(up('7777')).toBe('8000')
    expect(down('7777')).toBe('7000')
  })

  it('keeps the unit and the space around it', () => {
    expect(up('400 g')).toBe('450 g')
    expect(down('400 g')).toBe('350 g')
    expect(up('100mL')).toBe('150mL')
    expect(up('2 x 500 g')).toBe('3 x 500 g')
  })

  it('starts counting a quantity that is only a unit', () => {
    expect(up('kg')).toBe('1 kg')
    expect(up('')).toBe('1')
    expect(down('kg')).toBe('kg')
    expect(down('')).toBe('')
  })

  it('snaps a fractional quantity onto the grid', () => {
    expect(up('0.5 kg')).toBe('1 kg')
    expect(up('1.5 kg')).toBe('2 kg')
    expect(down('1.5 kg')).toBe('1 kg')
    expect(up('12.5 kg')).toBe('15 kg')
    expect(down('12.5 kg')).toBe('10 kg')
  })
})

describe('canStepDown', () => {
  it('is true only above the floor', () => {
    expect(canStepDown('2 kg')).toBe(true)
    expect(canStepDown('1.5 kg')).toBe(true)
    expect(canStepDown('1 kg')).toBe(false)
    expect(canStepDown('kg')).toBe(false)
    expect(canStepDown('')).toBe(false)
  })
})
